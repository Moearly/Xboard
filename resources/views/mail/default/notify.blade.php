<div style="margin:0;padding:0;background:#f4f7fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)">
        <tr>
          <td style="background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);padding:32px 40px;text-align:center">
            <div style="font-size:24px;font-weight:700;color:#fff;letter-spacing:1px">{{$name}}</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.8);margin-top:6px">服务通知</div>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px 20px">
            <div style="font-size:18px;font-weight:600;color:#1a1a2e;margin-bottom:16px">📢 服务通知</div>
            <div style="font-size:14px;color:#555;line-height:1.8">
              尊敬的用户您好，<br><br>
              {!! nl2br($content) !!}
            </div>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:16px 40px 28px">
            <a href="{{$url}}" style="display:inline-block;background:linear-gradient(135deg,#4facfe,#00f2fe);color:#fff;font-size:15px;font-weight:600;text-decoration:none;padding:14px 48px;border-radius:8px">查看详情 →</a>
          </td>
        </tr>
        <tr>
          <td style="border-top:1px solid #f0f0f0;padding:20px 40px;text-align:center">
            <a href="{{$url}}" style="font-size:13px;color:#4facfe;text-decoration:none">前往 {{$name}} →</a>
            <div style="font-size:11px;color:#ccc;margin-top:8px">此邮件由系统自动发送，请勿直接回复</div>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</div>
