<?php
// print_order.php - 주방 출력용 영수증 레이아웃
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

$order_id = $_GET['order_id'] ?? 0;

// 주문 정보와 매장 정보 조인
$sql = "SELECT o.*, s.store_name, s.tel as store_tel 
        FROM orders o 
        JOIN stores s ON o.store_id = s.id 
        WHERE o.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) die("주문 정보가 없습니다.");

$items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$order_id]);
$order_items = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Order Receipt #<?php echo $order_id; ?></title>
    <style>
        /* 영수증 전용 스타일 (80mm 규격) */
        @page { size: 80mm auto; margin: 0; }
        body { width: 80mm; margin: 0; padding: 5mm; font-family: 'Courier New', Courier, monospace; font-size: 14px; line-height: 1.2; color: #000; }
        .center { text-align: center; }
        .line { border-bottom: 1px dashed #000; margin: 5px 0; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .order-num { font-size: 32px; font-weight: bold; margin: 10px 0; border: 2px solid #000; display: inline-block; padding: 5px 15px; }
        .item-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 16px; margin-top: 5px; }
        .option-row { font-size: 12px; padding-left: 10px; margin-bottom: 5px; }
        .footer { font-size: 11px; margin-top: 15px; }
        
        /* 인쇄 버튼 숨김 */
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print();"> <div class="no-print" style="background:#ff0; padding:10px; text-align:center;">
        <button onclick="window.print()" style="font-weight:bold; padding:10px;">프린터로 인쇄하기</button>
        <button onclick="window.close()" style="padding:10px;">닫기</button>
    </div>

    <div class="center">
        <div class="title">Kitchen Order</div>
        <div class="order-num">#<?php echo $order['id']; ?></div>
        <div>Type: <?php echo strtoupper($order['order_type']); ?></div>
        <div>Time: <?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?></div>
    </div>

    <div class="line"></div>

    <?php foreach($order_items as $item): 
        $m_name = $pdo->query("SELECT menu_name FROM menu_translations WHERE menu_id = {$item['menu_id']} AND lang_code = 'ko'")->fetchColumn();
    ?>
    <div class="item-row">
        <span><?php echo $m_name; ?></span>
        <span>x<?php echo $item['quantity']; ?></span>
    </div>
    <?php if($item['options_text']): ?>
    <div class="option-row">
        + <?php echo str_replace(',', '<br>+ ', $item['options_text']); ?>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <div class="line"></div>

    <div class="footer">
        <?php if($order['order_type'] !== 'dinein'): ?>
            <div>ADDR: <?php echo $order['address']; ?></div>
            <div>TEL: <?php echo $order['tel']; ?></div>
        <?php endif; ?>
        <div class="center" style="margin-top:10px;">- Alrira Management System -</div>
    </div>
</body>
</html>