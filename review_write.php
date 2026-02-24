<?php
// review_write.php - 주문 기반 리뷰 작성
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$lang = $_GET['lang'] ?? 'ko';
$menu_id = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : null;

if ($order_id <= 0) {
    die('잘못된 접근입니다.');
}

// 주문 정보 + 매장 정보 가져오기
$stmt = $pdo->prepare("SELECT o.*, s.store_name FROM orders o JOIN stores s ON o.store_id = s.id WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die('주문을 찾을 수 없습니다.');
}

// 로그인 회원이면 세션에서 이름 가져오기, 아니면 guest_name 기본값 사용
$logged_in_user_id = $_SESSION['user_id'] ?? null;
$default_name = $logged_in_user_id ? ($_SESSION['nickname'] ?? '') : ($order['guest_name'] ?? '');

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $name = trim($_POST['name'] ?? $default_name);
    $image_path = null;

    if ($rating < 1 || $rating > 5 || $content === '') {
        $error = "평점과 내용을 모두 입력해 주세요.";
    } else {
        // 1) 사진 업로드 처리 (선택 사항)
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['photo']['tmp_name'];
            $mime = mime_content_type($tmp);

            // 지원 포맷: jpg/png/webp
            if (in_array($mime, ['image/jpeg','image/png','image/webp'])) {
                list($w, $h) = getimagesize($tmp);
                $maxW = 800;
                $maxH = 800;
                $scale = min($maxW / $w, $maxH / $h, 1);
                $newW = (int)($w * $scale);
                $newH = (int)($h * $scale);

                switch ($mime) {
                    case 'image/jpeg':
                        $src = imagecreatefromjpeg($tmp);
                        $ext = 'jpg';
                        break;
                    case 'image/png':
                        $src = imagecreatefrompng($tmp);
                        $ext = 'png';
                        break;
                    case 'image/webp':
                        $src = imagecreatefromwebp($tmp);
                        $ext = 'webp';
                        break;
                }

                if (isset($src)) {
                    $dst = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

                    $folder = 'uploads/reviews/';
                    if (!is_dir($folder)) {
                        mkdir($folder, 0755, true);
                    }

                    $filename = 'rev_' . $order_id . '_' . time() . '.' . $ext;
                    $fullpath = $folder . $filename;

                    if ($ext === 'jpg') {
                        imagejpeg($dst, $fullpath, 85);
                    } elseif ($ext === 'png') {
                        imagepng($dst, $fullpath);
                    } else {
                        imagewebp($dst, $fullpath, 85);
                    }

                    imagedestroy($src);
                    imagedestroy($dst);

                    $image_path = $fullpath;
                }
            }
        }

        // 2) 리뷰 저장 (회원이면 user_id, 아니면 guest_name)
        $ins = $pdo->prepare("
            INSERT INTO reviews (store_id, order_id, menu_id, user_id, guest_name, rating, content, image_path, lang_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $order['store_id'],
            $order_id,
            $menu_id,
            $logged_in_user_id,
            $logged_in_user_id ? null : $name,
            $rating,
            $content,
            $image_path,
            $lang
        ]);

        header("Location: menu.php?order_type=dinein&lang=" . urlencode($lang));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>리뷰 작성 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-md mx-auto bg-white rounded-[2.5rem] shadow-xl p-8 space-y-6">
        <h2 class="text-2xl font-black text-slate-800 tracking-tighter mb-2">
            <?php echo htmlspecialchars($order['store_name']); ?> 리뷰 작성
        </h2>
        <p class="text-[11px] text-slate-400 font-bold uppercase">
            주문번호 #<?php echo $order_id; ?>
        </p>

        <?php if($error): ?>
            <div class="bg-rose-50 text-rose-500 text-xs font-bold p-3 rounded-xl">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">이름 / 닉네임</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($default_name); ?>"
                       class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400"
                       placeholder="이름 또는 닉네임">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">평점 (1~5)</label>
                <select name="rating" class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <option value="5">★★★★★ (5)</option>
                    <option value="4">★★★★☆ (4)</option>
                    <option value="3">★★★☆☆ (3)</option>
                    <option value="2">★★☆☆☆ (2)</option>
                    <option value="1">★☆☆☆☆ (1)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">리뷰 내용</label>
                <textarea name="content" rows="4"
                          class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400"
                          placeholder="가맹점에 대한 후기를 자유롭게 작성해 주세요."></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">사진 (선택)</label>
                <input type="file" name="photo" accept="image/*"
                       class="w-full text-[11px] text-slate-500">
                <p class="text-[9px] text-slate-400 mt-1">최대 1장, 자동으로 크기 조정됩니다.</p>
            </div>

            <button type="submit"
                    class="w-full h-12 bg-slate-900 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-sky-500 transition">
                리뷰 등록
            </button>
        </form>
    </div>
</body>
 </html>