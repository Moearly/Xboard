<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/vite.svg" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>
  
  <!-- 全局设置 - 从Blade注入 -->
  <script>
    window.settings = {
      base_url: "/",
      title: "{{ $title }}",
      version: "{{ $version }}",
      logo: "{{ $logo }}",
      secure_path: "{{ $secure_path }}",
      api_base_url: "/api/v2/admin",
      debug: true
    };
  </script>
  
  <!-- XBoard 多语言翻译 -->
  <script>
    window.XBOARD_TRANSLATIONS = {
      'zh-CN': {
        'nav.dashboard': '仪表盘',
        'nav.systemManagement': '系统管理',
        'nav.systemConfig': '系统配置',
        'nav.themeConfig': '主题配置',
        'nav.pluginManagement': '插件管理',
        'nav.noticeManagement': '公告管理',
        'nav.paymentConfig': '支付配置',
        'nav.knowledgeManagement': '知识库管理',
        'nav.nodeManagement': '节点管理',
        'nav.permissionGroupManagement': '权限组管理',
        'nav.routeManagement': '路由管理',
        'nav.subscriptionManagement': '订阅管理',
        'nav.planManagement': '套餐管理',
        'nav.orderManagement': '订单管理',
        'nav.couponManagement': '优惠券管理',
        'nav.giftCardManagement': '礼品卡管理',
        'nav.userManagement': '用户管理',
        'nav.ticketManagement': '工单管理',
        'nav.trafficResetLogs': '流量重置日志',
        'nav.tenantManagement': '租户管理'
      }
    };
  </script>
  
  <!-- 前端资源 - 固定文件名，由 xboard-admin-source 构建 -->
  <script type="module" crossorigin src="/assets/admin/assets/index.js"></script>
  <link rel="modulepreload" crossorigin href="/assets/admin/assets/vendor.js">
  <link rel="modulepreload" crossorigin href="/assets/admin/assets/ui.js">
  <link rel="stylesheet" crossorigin href="/assets/admin/assets/index.css">
  <link rel="stylesheet" crossorigin href="/assets/admin/assets/vendor.css">
</head>
<body>
  <div id="root"></div>
</body>
</html>
