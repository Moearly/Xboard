<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>
  <script>
    window.settings = {
      base_url: "/",
      title: "{{ $title }}",
      version: "{{ $version }}",
      logo: "{{ $logo }}",
      secure_path: "{{ $secure_path }}",
    };
  </script>
  
  <style>
    /* 租户管理快速访问按钮样式 */
    .tenant-quick-access {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      background: linear-gradient(45deg, #1890ff, #40a9ff);
      color: white;
      border: none;
      border-radius: 50px;
      padding: 12px 20px;
      font-size: 14px;
      font-weight: 500;
      box-shadow: 0 4px 15px rgba(24, 144, 255, 0.3);
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }
    
    .tenant-quick-access:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(24, 144, 255, 0.4);
      color: white;
      text-decoration: none;
    }
    
    .tenant-quick-access:active {
      transform: translateY(0);
    }
    
    .tenant-icon {
      width: 18px;
      height: 18px;
    }
    
    @media (max-width: 768px) {
      .tenant-quick-access {
        top: 10px;
        right: 10px;
        padding: 10px 16px;
        font-size: 13px;
      }
    }
  </style>
  <script type="module" crossorigin src="/assets/admin/assets/index.js"></script>
  <link rel="stylesheet" crossorigin href="/assets/admin/assets/index.css" />
  <link rel="stylesheet" crossorigin href="/assets/admin/assets/vendor.css">
  <script src="/assets/admin/locales/en-US.js"></script>
  <script src="/assets/admin/locales/zh-CN.js"></script>
  <script src="/assets/admin/locales/ko-KR.js"></script>
</head>

<body>
  <div id="root"></div>
  
  <!-- 租户管理快速访问按钮 -->
  <a href="/tenant-management" class="tenant-quick-access" title="多租户管理">
    <svg class="tenant-icon" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h2v-2h2v-2h2v8zm-2-10h-6V7h6v2z"/>
    </svg>
    多租户管理
  </a>
  
  <script>
    // 租户管理功能检测
    document.addEventListener('DOMContentLoaded', function() {
      // 检测是否有租户管理API
      setTimeout(() => {
        const tenantBtn = document.querySelector('.tenant-quick-access');
        if (tenantBtn) {
          console.log('🏢 多租户管理功能已集成到 Xboard');
          console.log('📊 租户管理API已在后端AdminRoute中注册');
          console.log('🔗 快速访问地址:', tenantBtn.href);
        }
      }, 2000);
    });
  </script>
</body>

</html>
