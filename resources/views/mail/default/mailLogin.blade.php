<div style="margin:0;padding:0;background:#f4f7fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)">
        <tr>
          <td style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:32px 40px;text-align:center">
            <div style="font-size:24px;font-weight:700;color:#fff;letter-spacing:1px">{{$name}}</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.8);margin-top:6px">安全登录验证</div>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px 20px">
            <div style="font-size:18px;font-weight:600;color:#1a1a2e;margin-bottom:16px">🔐 登录验证</div>
            <div style="font-size:14px;color:#555;line-height:1.8">
              尊敬的用户您好，<br><br>
              您正在登录 <strong>{{$name}}</strong>，请在 <strong>5 分钟内</strong> 点击下方按钮完成验证。
            </div>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:8px 40px 24px">
            <a href="{{$link}}" style="display:inline-block;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:15px;font-weight:600;text-decoration:none;padding:14px 48px;border-radius:8px">确认登录 →</a>
          </td>
        </tr>
        <tr>
          <td style="padding:0 40px 8px">
            <div style="font-size:12px;color:#bbb;word-break:break-all">
              如按钮无法点击，请复制以下链接到浏览器：<br>
              <a href="{{$link}}" style="color:#667eea;text-decoration:none;font-size:11px">{{$link}}</a>
            </div>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 40px 32px">
            <div style="font-size:13px;color:#999;line-height:1.6">
              🔒 如果这不是您本人的操作，请忽略此邮件，您的账户仍然安全。
            </div>
          </td>
        </tr>
        <tr>
          <td style="border-top:1px solid #f0f0f0;padding:20px 40px;text-align:center">
            <a href="{{$url}}" style="font-size:13px;color:#667eea;text-decoration:none">前往 {{$name}} →</a>
            <div style="font-size:11px;color:#ccc;margin-top:8px">此邮件由系统自动发送，请勿直接回复</div>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</div>
