import { invoke } from '@tauri-apps/api/core';
import type { SessionInfo } from './types';

export const session = () => invoke<SessionInfo>('get_session');

export async function login(args: {
  apiBase: string; tenantSlug: string; email: string; password: string; deviceName: string;
}): Promise<void> {
  await invoke('device_login', args);
}

export const logout = () => invoke<void>('device_logout');

export async function request<T>(slug: string, method: 'GET' | 'POST', route: string, payload?: unknown): Promise<T> {
  const path = '/tenants/' + encodeURIComponent(slug) + route;
  return invoke<T>('api_request', { method, path, payload: payload ?? null });
}

export async function read<T>(slug: string, route: string): Promise<T> {
  return request<T>(slug, 'GET', route);
}

export async function write<T>(slug: string, route: string, payload: unknown): Promise<T> {
  return request<T>(slug, 'POST', route, payload);
}

export function friendlyError(error: unknown): string {
  if (typeof error === 'string') return error;
  if (error instanceof Error) return error.message;
  return 'Request failed. Please verify your connection and try again.';
}
