<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>绑定推送通知</title>
    <script src="https://res.wx.qq.com/open/js/jweixin-1.4.0.js"></script>
    <script src="/js/uni.webview.1.5.2.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, system-ui, sans-serif;
            background: #f5f5f7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 48px 32px;
            text-align: center;
            width: 320px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .icon { font-size: 56px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: 700; color: #1d1d1f; margin-bottom: 10px; }
        .desc  { font-size: 14px; color: #86868b; line-height: 1.6; }
        .btn {
            margin-top: 32px;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            background: #007aff;
            color: white;
        }
    </style>
</head>
<body>
<div class="card">
    <?php if ($code == 0): ?>
        <div class="icon">✅</div>
        <div class="title">绑定成功</div>
        <div class="desc">已开启公众号推送通知<br>喂养提醒和温度预警将实时推送</div>
        <button class="btn" onclick="onSuccess()">返回</button>
    <?php else: ?>
        <div class="icon">❌</div>
        <div class="title">绑定失败</div>
        <div class="desc"><?= htmlspecialchars($msg) ?></div>
        <button class="btn" style="background:#ff3b30;" onclick="onFail()">返回重试</button>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('UniAppJSBridgeReady', function () {
        <?php if ($code == 0): ?>
        onSuccess();
        <?php endif; ?>
    });

    function onSuccess() {
        uni.postMessage({ data: { status: 'success' } });
        uni.navigateBack({ delta: 1 });
    }

    function onFail() {
        uni.postMessage({ data: { status: 'fail' } });
        uni.navigateBack({ delta: 1 });
    }
</script>
</body>
</html>
