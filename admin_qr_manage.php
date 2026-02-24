<?php
// admin_qr_manage.php - 전체 소스 (이미지 깨짐 해결 버전)
include 'db_config.php';
include 'common.php';

// 데모용: 기본 1번 매장, 추후 선택 가능
$store_id = 1;

// 1. 상점 정보 가져오기
$stmt = $pdo->prepare("SELECT store_name FROM stores WHERE id = ?");
$stmt->execute([$store_id]);
$store = $stmt->fetch();

// 2. 현재 도메인 주소 자동 감지
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domain . "/index.php?store_id=" . $store_id;

// QR 생성 일자 (인쇄용 메타 정보)
$generated_at = date('Y-m-d');

// 3. 생성할 테이블 수
$total_tables = 10;
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR 코드 관리 - <?php echo $store['store_name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            .qr-grid { display: grid !important; grid-template-columns: repeat(2, 1fr) !important; gap: 20px !important; }
            .qr-item { border: 1px solid #e2e8f0 !important; page-break-inside: avoid; }
        }
    </style>
</head>
<body class="bg-slate-100">

    <div class="flex min-h-screen">
        <aside class="w-64 bg-slate-900 text-white p-6 no-print sticky top-0 h-screen">
            <h2 class="text-2xl font-black text-green-400 mb-8 italic">ALRIRA POS</h2>
            <nav class="space-y-4 text-sm font-bold">
                <a href="admin_dashboard.php" class="block py-3 px-4 rounded-xl text-slate-400 hover:bg-slate-800">대시보드</a>
                <a href="admin_menu_list.php" class="block py-3 px-4 rounded-xl text-slate-400 hover:bg-slate-800">메뉴 관리</a>
                <a href="admin_store_setting.php" class="block py-3 px-4 rounded-xl text-slate-400 hover:bg-slate-800">매장 설정</a>
                <a href="admin_qr_manage.php" class="block py-3 px-4 rounded-xl bg-green-500 text-slate-900">QR 코드 관리</a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-10 no-print">
                <div>
                    <h1 class="text-3xl font-black text-slate-800">테이블 QR 생성</h1>
                    <p class="text-slate-500">이미지 깨짐 현상을 해결한 새 버전입니다.</p>
                </div>
                <button onclick="window.print()" class="bg-slate-900 text-white px-8 py-4 rounded-2xl font-bold shadow-xl hover:bg-slate-800 transition">
                    🖨️ 테이블 QR 인쇄하기
                </button>
            </header>

            <div class="qr-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                <?php for($i=1; $i<=$total_tables; $i++): 
                    $order_link = $base_url . "&table_no=" . $i;
                ?>
                <div class="qr-item bg-white p-8 rounded-[2rem] shadow-sm text-center flex flex-col items-center border border-transparent">
                    <span class="text-[0.6rem] font-black text-slate-300 uppercase tracking-[0.3em] mb-1">Guest Table</span>
                    <h3 class="text-4xl font-black text-slate-900 mb-6"><?php echo $i; ?></h3>
                    
                    <div class="bg-slate-50 p-3 rounded-2xl mb-6">
                        <canvas id="qr-<?php echo $i; ?>"></canvas>
                    </div>
                    
                    <p class="text-[0.6rem] text-slate-400 font-mono break-all line-clamp-1 mb-1">
                        <?php echo $order_link; ?>
                    </p>
                    <p class="text-[0.55rem] text-slate-400 mb-2">
                        Generated: <?php echo $generated_at; ?>
                    </p>
                    <div class="w-10 h-1 bg-green-500 rounded-full"></div>
                    
                    <script>
                        new QRious({
                            element: document.getElementById('qr-<?php echo $i; ?>'),
                            value: '<?php echo $order_link; ?>',
                            size: 200,
                            level: 'H'
                        });
                    </script>
                </div>
                <?php endfor; ?>
            </div>
        </main>
    </div>

</body>
</html>