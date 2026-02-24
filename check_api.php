<?php
// check_api.php
$url = "https://open.er-api.com/v6/latest/KRW";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
$data = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

echo "<h2>API 접속 테스트 결과</h2>";
if ($data) {
    echo "<p style='color:green;'>✅ 접속 성공! 환율 데이터를 정상적으로 가져왔습니다.</p>";
} else {
    echo "<p style='color:red;'>❌ 접속 실패!</p>";
    echo "<p>에러 내용: " . $error . "</p>";
    echo "<p>HTTP 상태 코드: " . $info['http_code'] . "</p>";
}
?>