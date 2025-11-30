// 全局设置配置
window.settings = {
  // API 配置
  secure_path: '/d1428af5',
  base_url: 'http://38.55.193.181:7002',
  
  // 应用配置
  app_name: 'VpnAll Admin',
  app_version: '2.0.0',
  
  // 主题配置
  theme: 'light',
  
  // 语言配置
  locale: 'zh-CN',
  
  // 开发模式配置
  debug: true,
  useMockData: false, // 使用真实API
  
  // 其他配置
  currency: 'CNY',
  currency_symbol: '¥',
  
  // 功能开关
  features: {
    multi_tenant: true,
    payment_gateway: true,
    email_notifications: true,
    telegram_bot: true,
  }
}

// 开发环境特殊配置
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
  window.settings.debug = true
  window.settings.base_url = 'http://localhost:8080' // 直接使用localhost:8080
  window.settings.secure_path = 'admin' // 本地Docker的secure_path
  window.settings.useMockData = false // 使用真实API
}

console.log('[Settings] Global settings loaded:', window.settings)
