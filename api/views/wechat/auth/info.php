<?php
/**
 * Created by PhpStorm.
 * Author:ganxh
 * User: cp
 * Date: 2022/1/14
 * Time: 10:16
 */

?>
<html>
<head>
    <script type="text/javascript" src="https://res.wx.qq.com/open/js/jweixin-1.4.0.js"></script>
    <script type="text/javascript" src="/js/uni.webview.1.5.2.js"></script>
</head>

<body>

</body>

<script>
    document.addEventListener('UniAppJSBridgeReady', function () {
        uni.postMessage({
            data:"<?=$wechat_info_key?>"
        });
        uni.navigateBack({
            delta: 1
        });

    });
</script>
</html>
