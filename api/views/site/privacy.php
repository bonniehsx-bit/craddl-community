<?php
/**
 * @var $app_name string 应用名称
 * @var $company string 公司名称
 * @var $address string 公司地址 (审核必填)
 * @var $tel string 联系电话 (审核必填)
 * @var $email string 联系邮箱
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>隐私政策</title>
    <script>
        (function (doc, win) {
            var docEl = doc.documentElement,
                resizeEvt = 'onorientationchange' in window ? 'onorientationchange' : 'resize',
                recalc = function () {
                    var clientWidth = docEl.clientWidth;
                    if (!clientWidth) return;
                    docEl.style.fontSize = 25 * (clientWidth / 1920) + 'px';
                };
            if (!doc.addEventListener) return;
            win.addEventListener(resizeEvt, recalc, false);
            doc.addEventListener('DOMContentLoaded', recalc, false);
        })(document, window);
    </script>

    <style>
        /* 全局白色简约风格 */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            background-color: #FFFFFF;
            color: #333333;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.8;
        }

        #app {
            padding: 24px;
            max-width: 900px;
            margin: 0 auto;
            text-align: left;
            box-sizing: border-box;
        }

        p {
            word-break: break-all;
            margin-bottom: 1.2em;
            text-align: justify;
        }

        .page-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 30px;
            color: #000;
        }

        .section-title {
            font-size: 17px;
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #000;
            border-left: 4px solid #333;
            padding-left: 10px;
        }

        .sub-title {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 8px;
            color: #1a1a1a;
        }

        ul, ol {
            padding-left: 20px;
            margin: 10px 0;
        }

        li {
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .highlight {
            font-weight: bold;
            color: #000;
        }

        .red-text {
            color: #E64340; /* 仅用于警示注销不可逆 */
        }

        a {
            color: #1E6FFF;
            text-decoration: none;
            word-wrap: break-word;
        }

        .dev-info-box {
            background-color: #F9F9F9;
            padding: 20px;
            border-radius: 8px;
            margin-top: 40px;
            border: 1px solid #EEEEEE;
            font-size: 13px;
            line-height: 2;
        }

        hr {
            border: 0;
            border-top: 1px solid #EEEEEE;
            margin: 30px 0;
        }

        .notice-card {
            border: 1px solid #EEE;
            padding: 15px;
            margin-top: 10px;
            background-color: #FAFAFA;
        }
    </style>
</head>

<body>
<div id="app">
    <div class="page-title">隐私政策</div>

    <p>
        本政策仅适用于<strong><?= $company ?></strong>提供的<strong><?= $app_name ?></strong>产品或服务。
    </p>
    <p>
        <strong>最近更新日期：<?= date('Y年m月d日') ?></strong>
    </p>

    <p>本政策将帮助您了解以下内容：</p>
    <ul>
        <li>一、我们如何收集和使用您的用户信息</li>
        <li>二、我们如何使用 Cookie 和同类技术</li>
        <li>三、我们如何共享、转让、公开披露您的用户信息</li>
        <li>四、我们如何保护您的用户信息</li>
        <li>五、您的权利（含注销账号路径）</li>
        <li>六、我们如何处理儿童的个人信息</li>
        <li>七、您的用户信息如何储存及如何在全球范围转移</li>
        <li>八、本政策如何更新</li>
        <li>九、如何联系我们与开发者信息</li>
    </ul>

    <hr>

    <p>
        我们深知用户信息对您的重要性，并会尽全力保护您的用户信息安全可靠。我们致力于维持您对我们的信任，恪守权责一致原则、最少够用原则等。请在使用我们的产品（或服务）前，仔细阅读并了解本隐私政策。
    </p>

    <div class="section-title">一、我们如何收集和使用您的用户信息</div>

    <div class="sub-title">（一）为您提供基础业务功能所需收集的信息</div>
    <p>
        1. <strong>账号登录：</strong>当您注册或登录时，我们会收集您的手机号码，收集这些信息是为了帮助您完成注册，保障您的账号安全。
    </p>
    <p>
        2. <strong>设备及安全：</strong>为了保障软件服务的安全运行以及进行安全风控，我们会收集您的设备型号、设备标识符（<strong>包括IMEI、OAID、Android ID、MAC地址、SIM卡序列号、硬件序列号</strong>）、IP地址、应用列表、网络类型、<strong>传感器信息（如步数传感器、加速度传感器、陀螺仪传感器、重力传感器）</strong>以及<strong>Wi-Fi信息（包括Wi-Fi状态、Wi-Fi列表、SSID、BSSID）</strong>。这些信息是为您提供服务所必需的基础信息。
    </p>

    <div class="sub-title">（二）第三方SDK使用公示（重要）</div>
    <p>
        为保障App稳定运行并实现相关功能，我们接入了第三方SDK。<strong>我们承诺：所有SDK仅在您授权同意后、且在实现业务功能所必需的范围内收集信息，严禁任何静默收集行为。</strong>
    </p>

    <ul>
        <li>
            <strong>地图SDK (com.amap.api.maps)</strong><br>
            开发者：北京高德图强科技有限公司<br>
            使用目的：提供地图展示服务<br>
            收集信息：位置信息（经纬度、精确位置）、设备信息（IMEI/OAID/Android ID/MAC/IMSI）、网络状态<br>
            隐私链接：<a href="https://lbs.amap.com/pages/privacy/">https://lbs.amap.com/pages/privacy/</a>
        </li>
        <li>
            <strong>定位SDK (com.amap.api.location)</strong><br>
            开发者：北京高德图强科技有限公司<br>
            使用目的：提供精准定位服务<br>
            收集信息：位置信息、设备信息（IMEI/OAID/Android ID/MAC）、WiFi信息、网络状态<br>
            <strong>授权说明：</strong>如您拒绝授权定位，仅无法使用位置相关服务，不影响App基础功能的正常使用。<br>
            隐私链接：<a href="https://lbs.amap.com/pages/privacy/">https://lbs.amap.com/pages/privacy/</a>
        </li>
        <li>
            <strong>微信OpenSDK Android (com.tencent.mm.opensdk)</strong><br>
            开发者：深圳市腾讯计算机系统有限公司<br>
            使用目的：微信登录、信息分享、微信支付<br>
            收集信息：设备标识信息（OAID/IMEI）、网络状态、支付订单信息<br>
            隐私链接：<a href="https://support.weixin.qq.com/cgi-bin/mmsupportacctnodeweb-bin/pages/RYiYJkLOrQwu0nb8">https://support.weixin.qq.com/cgi-bin/mmsupportacctnodeweb-bin/pages/RYiYJkLOrQwu0nb8</a>
        </li>
        <li>
            <strong>移动智能终端补充设备标识体系统一调用SDK (com.bun.miitmdid)</strong><br>
            开发者：中国信息通信研究院<br>
            使用目的：获取OAID设备标识，用于安全风控及统计<br>
            收集信息：设备唯一标识符（OAID）<br>
            隐私链接：<a href="https://www.msa-alliance.cn/col.jsp?id=122">https://www.msa-alliance.cn/col.jsp?id=122</a>
        </li>
        <li>
            <strong>轻量版地图SDK (com.amap.api.maps.utils)</strong><br>
            开发者：北京高德图强科技有限公司<br>
            使用目的：地图辅助工具计算（如测距、经纬度转换）<br>
            收集信息：位置信息、设备信息、网络状态<br>
            隐私链接：<a href="https://lbs.amap.com/pages/privacy/">https://lbs.amap.com/pages/privacy/</a>
        </li>
        <li>
            <strong>个推/UniPush SDK</strong><br>
            开发者：每日互动股份有限公司<br>
            使用目的：提供消息推送服务<br>
            收集信息：设备信息（IMEI/MAC/Android ID/OAID）、网络信息<br>
            隐私链接：<a href="https://docs.getui.com/privacy/">https://docs.getui.com/privacy/</a>
        </li>
    </ul>

    <div class="section-title">二、我们如何使用 Cookie 和同类技术</div>
    <p>
        为确保网站正常运转，我们会在您的设备上存储名为 Cookie 的小数据文件。Cookie 能够存储您的访问偏好数据。我们不会将 Cookie 用于本政策所述目的之外的任何用途。
    </p>

    <div class="section-title">三、我们如何共享、转让、公开披露您的用户信息</div>
    <p>
        我们不会与第三方共享您的个人信息，但接入上述第三方SDK以实现必要业务功能的情形除外。我们仅会在获得您明确同意后，或基于法律法规要求的情况下公开披露您的信息。
    </p>

    <div class="section-title">四、我们如何保护您的用户信息</div>
    <p>
        我们采取了行业通用的安全防护措施（如加密传输SSL、数据匿名化、严格的内部访问权限）来保护您提供的用户信息，防止其遭到未经授权的访问、泄露或篡改。
    </p>

    <div class="section-title">五、您的权利（含注销账号路径）</div>
    <p>按照中国相关法律标准，我们保障您对自己的用户信息行使访问、更正及删除的权利。</p>
    <div class="notice-card">
        <p><strong>注销账号说明：</strong></p>
        <p>1. <strong>自助注销：</strong>请在App内前往 <strong>【我的】 — 【设置】 — 【注销账号】</strong> 提交申请。</p>
        <p>2. <strong>人工注销：</strong>如遇特殊情况，请联系客服电话 <strong><?= $tel ?></strong>。核实身份后，我们将在15个工作日内为您完成注销。</p>
        <p class="red-text">注意：账号一旦注销，所有数据将无法找回且不可逆，请谨慎操作。</p>
    </div>

    <div class="section-title">六、儿童信息保护</div>
    <p>
        本产品主要面向成年人。若您是未满14周岁的未成年人，请在监护人的陪同与同意下使用本产品。如果我们发现未经许可收集了儿童信息，将设法尽快删除。
    </p>

    <div class="section-title">七、信息的储存</div>
    <p>
        我们在中华人民共和国境内收集和产生的个人信息，将存储在境内。我们仅在提供产品服务所必需的期限内保留您的个人信息。
    </p>

    <div class="section-title">八、政策更新</div>
    <p>
        本政策可能适时修订。未经您明确同意，我们不会削减您在本政策下应享有的权利。修订后的隐私政策将通过App显著位置进行公示。
    </p>

    <div class="section-title">九、如何联系我们与开发者信息</div>
    <div class="dev-info-box">
        <p>如果您对本政策有任何疑问，请联系我们：</p>
        <strong>开发者信息：</strong><br>
        1. 开发者名称：<?= $company ?><br>
        2. 应用名称：<?= $app_name ?><br>
        3. 注册地址：<?= $address ?><br>
        4. 联系电话：<?= $tel ?><br>
        <?php if(!empty($email)): ?>
            5. 联系邮箱：<?= $email ?><br>
        <?php endif; ?>
    </div>

    <p style="text-align: center; color: #999; margin-top: 30px;">（完）</p>
    <br><br>
</div>
</body>
</html>