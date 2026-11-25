use serde::{Deserialize, Serialize};
use serde_json::{json, Value};
use std::{fs, path::PathBuf, time::Duration};
use tauri::{AppHandle, Manager};

const SECRET_SERVICE: &str = "com.web7softwares.poscloud.desktop";
const SECRET_ACCOUNT: &str = "current-device-token";

#[derive(Clone, Debug, Serialize, Deserialize)]
struct Settings {
    api_base: String,
    tenant_slug: String,
    device_uuid: String,
}

impl Default for Settings {
    fn default() -> Self {
        Self {
            api_base: "https://pos.web7softwares.com/api/v1".into(),
            tenant_slug: String::new(),
            device_uuid: uuid::Uuid::new_v4().to_string(),
        }
    }
}

fn settings_path(app: &AppHandle) -> Result<PathBuf, String> {
    let directory = app.path().app_config_dir().map_err(|e| e.to_string())?;
    fs::create_dir_all(&directory).map_err(|e| e.to_string())?;
    Ok(directory.join("desktop-settings.json"))
}

fn settings(app: &AppHandle) -> Result<Settings, String> {
    let file = settings_path(app)?;
    if !file.exists() {
        return Ok(Settings::default());
    }
    let content = fs::read_to_string(file).map_err(|e| e.to_string())?;
    serde_json::from_str::<Settings>(&content)
        .map_err(|_| "Desktop configuration is invalid. Sign in again after restoring settings.".into())
}

fn write_settings(app: &AppHandle, data: &Settings) -> Result<(), String> {
    let path = settings_path(app)?;
    // This file stores NON-sensitive settings only. Bearer tokens are in Windows Credential Manager.
    fs::write(path, serde_json::to_vec(data).map_err(|e| e.to_string())?)
        .map_err(|e| e.to_string())
}

fn credential() -> Result<keyring::Entry, String> {
    keyring::Entry::new(SECRET_SERVICE, SECRET_ACCOUNT)
        .map_err(|e| format!("Windows credential store is unavailable: {e}"))
}

fn current_token() -> Result<Option<String>, String> {
    let entry = credential()?;
    match entry.get_password() {
        Ok(token) => Ok(Some(token)),
        Err(keyring::Error::NoEntry) => Ok(None),
        Err(e) => Err(format!("Unable to access Windows credentials: {e}")),
    }
}

fn delete_token() -> Result<(), String> {
    let entry = credential()?;
    match entry.delete_credential() {
        Ok(()) | Err(keyring::Error::NoEntry) => Ok(()),
        Err(e) => Err(format!("Unable to clear device credentials: {e}")),
    }
}

fn normalized_api_base(text: &str) -> Result<String, String> {
    let mut parsed = url::Url::parse(text.trim())
        .map_err(|_| "Enter a valid API base address.".to_string())?;
    let local = matches!(parsed.host_str(), Some("localhost") | Some("127.0.0.1"));
    if parsed.scheme() != "https" && !(local && parsed.scheme() == "http") {
        return Err("HTTPS is required (except for localhost development).".into());
    }
    if parsed.username() != "" || parsed.password().is_some() || parsed.query().is_some()
        || parsed.fragment().is_some() {
        return Err("Remove credentials, query strings and fragments from the API URL.".into());
    }
    let path = parsed.path().trim_end_matches('/');
    if path != "/api/v1" {
        return Err("The API address must end with /api/v1.".into());
    }
    parsed.set_query(None);
    parsed.set_fragment(None);
    Ok(parsed.as_str().trim_end_matches('/').to_string())
}

fn http() -> Result<reqwest::Client, String> {
    reqwest::Client::builder()
        .timeout(Duration::from_secs(20))
        .connect_timeout(Duration::from_secs(10))
        .build()
        .map_err(|e| e.to_string())
}

fn response_error(body: &Value, status: reqwest::StatusCode) -> String {
    if let Some(errors) = body.get("errors").and_then(Value::as_object) {
        if let Some(message) = errors.values().flat_map(|v| v.as_array().into_iter().flatten())
            .find_map(Value::as_str) {
            return message.to_owned();
        }
    }
    body.get("message").and_then(Value::as_str)
        .unwrap_or(status.canonical_reason().unwrap_or("Server error"))
        .to_owned()
}

async fn unpack(response: reqwest::Response) -> Result<Value, String> {
    let status = response.status();
    let body = response.json::<Value>().await
        .map_err(|_| format!("Unexpected server response ({status})."))?;
    if !status.is_success() {
        return Err(format!("{} ({})", response_error(&body, status), status.as_u16()));
    }
    Ok(body)
}

#[tauri::command]
fn get_session(app: AppHandle) -> Result<Value, String> {
    let config = settings(&app)?;
    let has_token = current_token()?.is_some();
    Ok(json!({
        "api_base": config.api_base,
        "tenant_slug": config.tenant_slug,
        "authenticated": has_token && !config.tenant_slug.is_empty()
    }))
}

#[tauri::command]
async fn device_login(
    app: AppHandle,
    api_base: String,
    tenant_slug: String,
    email: String,
    password: String,
    device_name: String,
) -> Result<Value, String> {
    let base = normalized_api_base(&api_base)?;
    let slug = tenant_slug.trim();
    if slug.is_empty() || slug.len() > 190 || !slug.chars().all(|c| c.is_ascii_alphanumeric() || c == '-' || c == '_') {
        return Err("Enter a valid business slug.".into());
    }
    let mut config = settings(&app)?;
    if config.tenant_slug != slug || config.api_base != base {
        config.device_uuid = uuid::Uuid::new_v4().to_string();
    }
    let payload = json!({
        "tenant_slug": slug,
        "email": email.trim(),
        "password": password,
        "device_uuid": config.device_uuid,
        "device_name": device_name.trim(),
        "platform": "desktop",
        "app_version": env!("CARGO_PKG_VERSION")
    });
    let response = http()?.post(format!("{base}/auth/device-login"))
        .header("Accept", "application/json")
        .json(&payload)
        .send().await.map_err(|e| format!("Could not connect to POS Cloud: {e}"))?;
    let mut body = unpack(response).await?;
    let token = body.get_mut("data").and_then(Value::as_object_mut)
        .and_then(|data| data.remove("access_token"))
        .and_then(|value| value.as_str().map(str::to_owned))
        .ok_or("Server did not provide an access token.")?;
    credential()?.set_password(&token)
        .map_err(|e| format!("Failed to protect the token in Windows Credential Manager: {e}"))?;
    config.api_base = base;
    config.tenant_slug = slug.to_owned();
    write_settings(&app, &config)?;
    // Never return bearer tokens to the JavaScript renderer.
    Ok(body)
}

#[tauri::command]
async fn api_request(
    app: AppHandle,
    method: String,
    path: String,
    payload: Option<Value>,
) -> Result<Value, String> {
    let config = settings(&app)?;
    let token = current_token()?.ok_or("Your session is not active. Sign in again.")?;
    if config.tenant_slug.is_empty() {
        return Err("Select a business first.".into());
    }
    let root = format!("/tenants/{}/", config.tenant_slug);
    if !path.starts_with(&root) || path.contains("..") || path.contains('\\')
        || path.contains('#') || path.starts_with("//") {
        return Err("Invalid API route.".into());
    }
    let mut request = match method.as_str() {
        "GET" => http()?.get(format!("{}{}", config.api_base, path)),
        "POST" => http()?.post(format!("{}{}", config.api_base, path)),
        _ => return Err("Unsupported request method.".into()),
    };
    request = request.header("Accept", "application/json").bearer_auth(token);
    if let Some(body) = payload {
        request = request.json(&body);
    }
    let response = request.send().await
        .map_err(|e| format!("Unable to reach the server. No offline transaction was submitted: {e}"))?;
    if response.status() == reqwest::StatusCode::UNAUTHORIZED {
        let _ = delete_token();
        return Err("Your device session expired or was revoked. Sign in again.".into());
    }
    unpack(response).await
}

#[tauri::command]
async fn device_logout(app: AppHandle) -> Result<(), String> {
    let config = settings(&app)?;
    if let Some(token) = current_token()? {
        // A server-side revoke is attempted; local token removal is mandatory even if offline.
        if !config.tenant_slug.is_empty() {
            let uri = format!("{}/tenants/{}/auth/logout", config.api_base, config.tenant_slug);
            if let Ok(client) = http() {
                let _ = client.post(uri).header("Accept", "application/json")
                    .bearer_auth(token).send().await;
            }
        }
    }
    delete_token()
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .invoke_handler(tauri::generate_handler![
            get_session,
            device_login,
            api_request,
            device_logout
        ])
        .run(tauri::generate_context!())
        .expect("Failed to launch POS Cloud Desktop");
}
