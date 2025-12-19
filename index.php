<?php
// ==========================================
// 1. 環境変数の読み込み (.env parser without libraries)
// ==========================================
$apiKey = '';
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // コメントをスキップ
        list($name, $value) = explode('=', $line, 2);
        if (trim($name) === 'OPENAI_API_KEY') {
            $apiKey = trim($value);
        }
    }
}

// ==========================================
// 2. フォーム送信時の処理
// ==========================================
$result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['query'])) {
    if (empty($apiKey)) {
        $result = 'エラー: .env ファイルに OPENAI_API_KEY が設定されていません。';
    } else {
        $userInput = $_POST['query'];

        // APIリクエストの設定
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => 'gpt-5-mini', // 指定されたモデル
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a system that extracts exactly one most important keyword from the user input. Output ONLY the original word and its English translation in plain text. Format: "Original (English)". Do not output anything else.'
                ],
                [
                    'role' => 'user',
                    'content' => $userInput
                ]
            ],
            'temperature' => 0.3,
        ];

        // cURLによるAPIコール (ライブラリ不使用)
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // レスポンス処理
        if ($httpCode === 200) {
            $json = json_decode($response, true);
            $content = $json['choices'][0]['message']['content'] ?? '解析できませんでした';
            $result = trim($content);
        } else {
            // エラー時のデバッグ用メッセージ
            $result = "API Error (Code: $httpCode): " . ($error ?: $response);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重要語抽出 (GPT-5 Mini)</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        textarea { width: 100%; height: 100px; margin-bottom: 1rem; padding: 0.5rem; }
        button { padding: 0.5rem 2rem; cursor: pointer; }
        .result { margin-top: 2rem; padding: 1rem; background: #f4f4f4; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <h2>重要語抽出ツール</h2>
    <form method="post">
        <textarea name="query" placeholder="ここに文章を入力してください..."><?= htmlspecialchars($_POST['query'] ?? '', ENT_QUOTES) ?></textarea><br>
        <button type="submit">抽出・翻訳する</button>
    </form>

    <?php if ($result): ?>
        <div class="result">
            結果: <?= htmlspecialchars($result, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>
</body>
</html>