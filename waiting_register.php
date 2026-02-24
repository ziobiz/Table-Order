<?php
// waiting_register.php - 손님용 대기 등록 (태블릿 모드)
include 'db_config.php';

$store_id = $_GET['store_id'] ?? 1; // 실제로는 해당 매장의 ID 세팅

if (isset($_POST['register_waiting'])) {
    $name = $_POST['customer_name'];
    $tel = $_POST['tel'];
    $size = $_POST['party_size'];

    // 오늘 해당 매장의 마지막 대기 번호 가져오기
    $stmt = $pdo->prepare("SELECT MAX(waiting_num) FROM waiting_list WHERE store_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$store_id]);
    $last_num = $stmt->fetchColumn() ?: 0;
    $new_num = $last_num + 1;

    $ins = $pdo->prepare("INSERT INTO waiting_list (store_id, customer_name, tel, party_size, waiting_num) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$store_id, $name, $tel, $size, $new_num]);

    echo "<script>alert('대기 등록 완료! 고객님의 번호는 {$new_num}번입니다.'); location.href='waiting_register.php?store_id=$store_id';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiting Register - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-white flex flex-col items-center justify-center min-h-screen p-6">
    <div class="max-w-md w-full text-center space-y-8">
        <h1 class="text-5xl font-black italic text-sky-400 tracking-tighter uppercase">Waiting</h1>
        <p class="text-slate-400 font-bold tracking-widest text-xs">현재 대기 팀: 
            <span class="text-white text-xl ml-2"><?php 
                $count = $pdo->prepare("SELECT COUNT(*) FROM waiting_list WHERE store_id = ? AND status = 'waiting'");
                $count->execute([$store_id]);
                echo $count->fetchColumn();
            ?></span> 팀
        </p>

        <form method="POST" class="bg-white p-10 rounded-[3rem] space-y-6 shadow-2xl">
            <input type="text" name="customer_name" placeholder="성함을 입력하세요" required class="w-full p-5 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 text-slate-900 font-bold text-lg outline-none focus:ring-4 focus:ring-sky-500/20">
            <input type="tel" name="tel" placeholder="연락처 (010-0000-0000)" required class="w-full p-5 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 text-slate-900 font-bold text-lg outline-none focus:ring-4 focus:ring-sky-500/20">
            <div class="flex items-center justify-between px-2">
                <label class="text-slate-500 font-black uppercase text-xs">인원수</label>
                <input type="number" name="party_size" value="2" min="1" class="w-20 p-3 bg-slate-100 rounded-xl text-slate-900 font-black text-center outline-none">
            </div>
            <button type="submit" name="register_waiting" class="w-full p-6 bg-sky-500 text-white rounded-3xl font-black text-xl uppercase tracking-widest shadow-xl shadow-sky-500/30 hover:bg-sky-600 transition-all">대기 등록하기</button>
        </form>
    </div>
</body>
</html>