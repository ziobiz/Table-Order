<?php
// reservation_register.php - 손님용 온라인 예약 등록 (태블릿/모바일용)
include 'db_config.php';

$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_reservation'])) {
    $name = trim($_POST['customer_name'] ?? '');
    $tel = trim($_POST['tel'] ?? '');
    $size = (int)($_POST['party_size'] ?? 2);
    $date = trim($_POST['reserve_date'] ?? '');
    $time = trim($_POST['reserve_time'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($name === '' || $tel === '' || $date === '' || $time === '' || $size <= 0) {
        echo "<script>alert('이름, 연락처, 날짜, 시간, 인원은 필수입니다.'); history.back();</script>";
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO reservations (store_id, user_id, customer_name, tel, party_size, reserve_date, reserve_time, status, note) VALUES (?, NULL, ?, ?, ?, ?, ?, 'PENDING', ?)");
        $stmt->execute([$store_id, $name, $tel, $size, $date, $time, $note]);
        $when = $date . ' ' . $time;
        echo "<script>alert('예약 접수 완료!\\n예약일시: {$when}\\n인원: {$size}명'); location.href='reservation_register.php?store_id={$store_id}';</script>";
        exit;
    } catch (Exception $e) {
        echo "<script>alert('예약 저장 중 오류가 발생했습니다. 관리자에게 문의해 주세요.'); history.back();</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 flex flex-col items-center justify-center min-h-screen p-6">
    <div class="max-w-md w-full text-center space-y-8">
        <h1 class="text-4xl font-black italic text-slate-900 tracking-tighter uppercase">Reservation</h1>
        <p class="text-slate-400 font-bold tracking-widest text-[10px] uppercase">온라인 예약 · 방문 전 미리 좌석 확보</p>

        <form method="POST" class="bg-white p-8 rounded-[2.5rem] space-y-6 shadow-2xl border border-slate-100 text-left">
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase mb-2">이름</label>
                <input type="text" name="customer_name" placeholder="성함을 입력하세요" required class="w-full p-4 bg-slate-50 rounded-2xl border border-slate-200 text-slate-900 font-bold text-sm outline-none focus:ring-2 focus:ring-sky-400">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase mb-2">연락처</label>
                <input type="tel" name="tel" placeholder="010-0000-0000" required class="w-full p-4 bg-slate-50 rounded-2xl border border-slate-200 text-slate-900 font-bold text-sm outline-none focus:ring-2 focus:ring-sky-400">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-2">날짜</label>
                    <input type="date" name="reserve_date" required class="w-full p-3 bg-slate-50 rounded-2xl border border-slate-200 text-slate-900 font-bold text-sm outline-none focus:ring-2 focus:ring-sky-400">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-2">시간</label>
                    <input type="time" name="reserve_time" required class="w-full p-3 bg-slate-50 rounded-2xl border border-slate-200 text-slate-900 font-bold text-sm outline-none focus:ring-2 focus:ring-sky-400">
                </div>
            </div>
            <div class="flex items-center justify-between">
                <label class="text-[10px] font-black text-slate-500 uppercase">인원수</label>
                <input type="number" name="party_size" value="2" min="1" class="w-20 p-3 bg-slate-50 rounded-xl border border-slate-200 text-slate-900 font-black text-center outline-none focus:ring-2 focus:ring-sky-400">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase mb-2">요청사항 (선택)</label>
                <textarea name="note" rows="2" class="w-full p-3 bg-slate-50 rounded-2xl border border-slate-200 text-slate-900 text-sm outline-none focus:ring-2 focus:ring-sky-400" placeholder="알레르기, 좌석 요청 등"></textarea>
            </div>
            <button type="submit" name="register_reservation" class="w-full p-5 bg-sky-500 text-white rounded-[2rem] font-black text-sm uppercase tracking-widest shadow-lg shadow-sky-300 hover:bg-sky-600 transition-all">
                예약 신청하기
            </button>
        </form>
    </div>
</body>
</html>

