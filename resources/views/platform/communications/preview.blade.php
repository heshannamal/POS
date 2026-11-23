<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Email preview</title>
<style>
body{background:#f3f4f8;font-family:Arial,sans-serif;color:#253047;margin:0;padding:38px 12px}
.preview{max-width:680px;margin:auto;border:1px solid #dddfe7;background:white;border-radius:20px;overflow:hidden;box-shadow:0 18px 50px #1e293b12}
.head{padding:24px 30px;background:#14223d;color:white}
.head small{display:block;opacity:.72;margin-bottom:8px}
.head strong{font-size:18px;line-height:1.45}
.body{padding:28px 30px;line-height:1.7;overflow-wrap:anywhere}
a{color:#355fbd}
code{background:#f6f6fb;border-radius:5px;padding:2px 5px}
.note{text-align:center;color:#667085;font-size:12px;margin-top:16px}
</style>
</head>
<body>
<div class="preview">
    <div class="head"><small>POS CLOUD · EMAIL PREVIEW</small><strong>{{ $subject }}</strong></div>
    <div class="body">{!! $html !!}</div>
</div>
<p class="note">Preview only. No email was sent. Dynamic variables are intentionally left unchanged.</p>
</body>
</html>
