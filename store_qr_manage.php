<?php
// store_qr_manage.php - 가맹점주가 테이블별로 주문용 QR을 생성하는 페이지 (단순 생성 버전)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

// 현재 도메인 감지 (QR 찍었을 때 접속할 주소)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$base_url = "$protocol://$domain";

// QR 생성 일자 (출력용, 실제 QR 데이터는 고정된 URL만 포함)
$generated_at = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Order QR Generator - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            .print-area { display: grid !important; grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .qr-card { break-inside: avoid; border: 1px solid #eee !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-[96rem] mx-auto">
        
        <div class="no-print bg-white p-8 rounded-[2rem] shadow-xl border border-slate-100 mb-10">
            <h1 class="text-2xl font-black text-slate-900 mb-2 uppercase italic">Table QR Generator</h1>
            <p class="text-sm text-slate-400 mb-8 font-bold uppercase">테이블 부착용 주문 QR 코드 생성</p>
            
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">테이블 수 (범위)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" id="start_no" value="1" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold text-center">
                        <span class="text-slate-300 font-bold">~</span>
                        <input type="number" id="end_no" value="10" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold text-center">
                    </div>
                </div>
                <button onclick="generate()" class="bg-violet-600 text-white px-10 py-4 rounded-2xl font-black shadow-lg hover:bg-violet-700 transition-all">GENERATE</button>
                <button onclick="window.print()" class="bg-slate-900 text-white px-8 py-4 rounded-2xl font-black shadow-lg hover:bg-slate-800">PRINT</button>
            </div>
        </div>

        <div id="print-zone" class="print-area grid grid-cols-1 md:grid-cols-2 gap-6">
            </div>

    </div>

    <script>
    function generate() {
        const start = parseInt(document.getElementById('start_no').value);
        const end = parseInt(document.getElementById('end_no').value);
        const zone = document.getElementById('print-zone');
        const storeName = "<?php echo $store_name; ?>";
        const generatedAt = "<?php echo $generated_at; ?>";
        // QR 스캔 시 진입할 URL (index.php에서 store_id, table_no 인식)
        const targetUrlBase = "<?php echo $base_url; ?>/index.php?store_id=<?php echo $store_id; ?>";

        zone.innerHTML = ""; // 기존 내역 삭제

        for (let i = start; i <= end; i++) {
            const card = document.createElement('div');
            card.className = "qr-card bg-white p-6 rounded-[2rem] shadow-md border border-slate-100 flex items-center gap-6 relative overflow-hidden";
            card.innerHTML = `
                <div class="absolute top-0 right-0 bg-sky-500 text-white text-[10px] font-black px-4 py-1 rounded-bl-xl uppercase">Table ${i}</div>
                <div id="qr_${i}" class="bg-white p-2 rounded-xl flex-shrink-0"></div>
                <div class="flex-1">
                    <h3 class="text-lg font-black text-slate-900 leading-tight">SCAN TO<br>ORDER</h3>
                    <p class="text-[9px] text-slate-400 font-bold mt-2 uppercase tracking-tighter">앉으신 자리에서 주문하세요</p>
                    <div class="mt-3 text-[10px] font-black text-sky-500 uppercase border-t pt-2 border-slate-50">
                        ${storeName}
                        <span class="block text-[9px] text-slate-400 font-normal mt-1">Generated: ${generatedAt}</span>
                    </div>
                </div>
            `;
            zone.appendChild(card);

            // 실제 QR 코드 생성
            new QRCode(document.getElementById(`qr_${i}`), {
                text: `${targetUrlBase}&table_no=${i}`,
                width: 90,
                height: 90,
                colorDark : "#0f172a",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }
    }
    </script>
</body>
</html>