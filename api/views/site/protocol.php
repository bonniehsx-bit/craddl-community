<?php
/**
 * @var $app_name string 应用名称
 * @var $app_short_name string 应用简称
 * @var $company string 公司名称
 * @var $address string 公司地址
 * @var $tel string 联系电话
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>用户协议</title>
    <script>
        (function (doc, win) {
            var docEl = doc.documentElement,
                resizeEvt = 'onorientationchange' in window ? 'onorientationchange' : 'resize',
                recalc = function () {
                    var clientWidth = docEl.clientWidth;
                    if (!clientWidth) return;
                    // 自适应字体大小，基准宽度 1920
                    docEl.style.fontSize = 25 * (clientWidth / 1920) + 'px';
                };
            if (!doc.addEventListener) return;
            win.addEventListener(resizeEvt, recalc, false);
            doc.addEventListener('DOMContentLoaded', recalc, false);
        })(document, window);
    </script>

    <style>
        /* 基础样式重置，确保白底黑字 */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: #FFFFFF;
            color: #333333;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.8;
        }

        #app {
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
            text-align: left;
            box-sizing: border-box;
        }

        p {
            word-break: break-all;
            margin-bottom: 1em;
            text-align: justify;
        }

        /* 标题样式 */
        .page-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #000;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 10px;
            color: #000;
            border-left: 4px solid #333;
            padding-left: 10px;
            line-height: 1.2;
        }

        /* 列表样式 */
        ol {
            padding-left: 20px;
            margin: 10px 0;
        }

        li {
            margin-bottom: 5px;
        }

        /* 开发者信息框样式 */
        .dev-info-box {
            background-color: #F8F8F8;
            padding: 15px;
            border-radius: 5px;
            margin-top: 40px;
            border: 1px solid #EEEEEE;
            font-size: 13px;
        }
    </style>
</head>

<body>
<div id="app">
    <div class="page-title">用户协议</div>

    <p>
        本协议是您与“<strong><?= $app_name ?></strong>”的所有者“<strong><?= $company ?></strong>”(以下简称为“<strong><?= $app_short_name ?></strong>”)之间就<?= $app_short_name ?>所提供的服务及相关事宜所立订的契约。请您仔细阅读本注册协议，在您确认阅读并选择注册后，本协议即构成对双方有约束力的法律文件。
    </p>

    <div class="section-title">第一条 服务条款的确认和接纳</div>
    <p>
        1. <?= $app_short_name ?>的各项电子服务的所有权和运作权归“<?= $app_short_name ?>”所有。客户同意所有注册协议条款并完成注册程序，才能成为<?= $app_short_name ?>的正式客户。客户确认：本协议条款是处理双方权利义务的契约，始终有效，法律另有强制性规定或双方另有特别约定的，依其规定。
    </p>
    <p>
        2. 客户点击同意本协议的，即视为客户确认自己具有享受<?= $app_short_name ?>服务、下单购物等相应的权利能力和行为能力，能够独立承担法律责任。
    </p>
    <p>
        3. 如果您在18周岁以下，您只能在父母或监护人的监护参与下才能使用<?= $app_short_name ?>。
    </p>
    <p>
        4. “<?= $app_short_name ?>”保留在中华人民共和国大陆地区法施行之法律允许的范围内独自决定拒绝服务、关闭客户账户、清除或编辑内容或取消订单的权利。
    </p>

    <div class="section-title">第二条 法律管辖和适用</div>
    <p>
        本协议的订立、执行和解释及争议的解决均应适用在中华人民共和国大陆地区适用之有效法律（但不包括其冲突法规则）。如发生<?= $app_short_name ?>服务条款与适用之法律相抵触时，则这些条款将完全按法律规定重新解释，而其它有效条款继续有效。
        如缔约方就本协议内容或其执行发生任何争议，双方应尽力友好协商解决；协商不成时，任何一方均可向有管辖权的中华人民共和国大陆地区法院提起诉讼。
    </p>

    <div class="section-title">第三条 <?= $app_short_name ?>服务</div>
    <p>
        1. <?= $app_short_name ?>通过互联网依法为客户提供互联网信息等服务，客户在完全同意本协议及<?= $app_short_name ?>规定的情况下，方有权使用<?= $app_short_name ?>的相关服务。
    </p>
    <p>
        2. 客户必须自行准备如下设备和承担如下开支：<br>
        (1) 上网设备，包括并不限于电脑或者其他上网终端、调制解调器及其他必备的上网装置；<br>
        (2) 上网开支，包括并不限于网络接入费、上网设备租用费、手机流量费等。
    </p>

    <div class="section-title">第四条 客户信息</div>
    <p>
        1. 客户应自行诚信向<?= $app_short_name ?>提供注册资料，客户同意其提供的注册资料真实、准确、完整、合法有效，客户注册资料如有变动的，应及时更新其注册资料。如果客户提供的注册资料不合法、不真实、不准确、不详尽的，客户需承担因此引起的相应责任及后果，并且“<?= $app_short_name ?>”保留终止客户使用“<?= $app_short_name ?>”各项服务的权利。
    </p>
    <p>
        2. 客户在<?= $app_short_name ?>进行浏览、下单购物等活动时，涉及客户真实姓名/名称、通信地址、联系电话、电子邮箱等隐私信息的，<?= $app_short_name ?>将予以严格保密，除非得到客户的授权或法律另有规定，<?= $app_short_name ?>不会向外界披露客户隐私信息。
    </p>
    <p>
        3. 客户注册成功后，将产生客户名和密码等账户信息，您可以根据<?= $app_short_name ?>规定改变您的密码。客户应谨慎合理的保存、使用其客户名和密码。客户若发现任何非法使用客户账号或存在安全漏洞的情况，请立即通知<?= $app_short_name ?>并向公安机关报案。
    </p>
    <p>
        4. 客户同意，“<?= $app_short_name ?>”拥有通过邮件、短信电话等形式，向在<?= $app_short_name ?>注册、购物客户、收货人发送订单信息、促销活动等告知信息的权利。
    </p>
    <p>
        5. 客户不得将在<?= $app_short_name ?>注册获得的账户借给他人使用，否则客户应承担由此产生的全部责任，并与实际使用人承担连带责任。
    </p>

    <div class="section-title">第五条 客户依法言行义务</div>
    <p>本协议依据国家相关法律法规规章制定，客户同意严格遵守以下义务：</p>
    <p>
        1. 不得传输或发表：煽动抗拒、破坏宪法和法律、行政法规实施的言论，煽动颠覆国家政权，推翻社会主义制度的言论，煽动分裂国家、破坏国家统一的的言论，煽动民族仇恨、民族歧视、破坏民族团结的言论；<br>
        2. 从中国大陆向境外传输资料信息时必须符合中国有关法规；<br>
        3. 不得利用<?= $app_short_name ?>从事洗钱、窃取商业秘密、窃取个人信息等违法犯罪活动；<br>
        4. 不得干扰<?= $app_short_name ?>的正常运转，不得侵入<?= $app_short_name ?>及国家计算机信息系统；<br>
        5. 不得传输或发表任何违法犯罪的、骚扰性的、中伤他人的、辱骂性的、恐吓性的、伤害性的、庸俗的，淫秽的、不文明的等信息资料；<br>
        6. 不得传输或发表损害国家社会公共利益和涉及国家安全的信息资料或言论；<br>
        7. 不得教唆他人从事本条所禁止的行为；<br>
        8. 不得利用在<?= $app_short_name ?>注册的账户进行牟利性经营活动；<br>
        9. 不得发布任何侵犯他人著作权、商标权等知识产权或合法权利的内容；
    </p>
    <p>
        <?= $app_short_name ?>保有删除站内各类不符合法律政策或不真实的信息内容而无须通知客户的权利。若客户未遵守以上规定的，<?= $app_short_name ?>有权作出独立判断并采取暂停或关闭客户帐号等措施。客户须对自己在网上的言论和行为承担法律责任。
    </p>

    <div class="section-title">第六条 商品信息</div>
    <p>
        <?= $app_short_name ?>上的商品价格、数量、是否有货等商品信息随时都有可能发生变动，<?= $app_short_name ?>不作特别通知。由于网站上商品信息的数量极其庞大，虽然<?= $app_short_name ?>会尽最大努力保证您所浏览商品信息的准确性，但由于众所周知的互联网技术因素等客观原因存在，<?= $app_short_name ?>网页显示的信息可能会有一定的滞后性或差错，对此情形您知悉并理解；“<?= $app_short_name ?>”欢迎纠错，并会视情况给予纠错者一定的奖励。
    </p>

    <div class="section-title">第七条 订单</div>
    <p>
        1. 在您下订单时，请您仔细确认所购商品的名称、价格、数量、型号、规格、尺寸、联系地址、电话、收货人等信息。收货人与客户本人不一致的，收货人的行为和意思表示视为客户的行为和意思表示，客户应对收货人的行为及意思表示的法律后果承担连带责任。
    </p>
    <p>
        2. 除法律另有强制性规定外，双方约定如下：<?= $app_short_name ?>上销售方展示的商品和价格等信息仅仅是要约邀请，您下单时须填写您希望购买的商品数量、价款及支付方式、收货人、联系方式、收货地址(合同履行地点)、合同履行方式等内容；系统生成的订单信息是计算机信息系统根据您填写的内容自动生成的数据，仅是您向销售方发出的合同要约；销售方收到该订单信息后，已将货物实际直接向您发出时，方视为您与销售方之间就发出的货物建立了合同关系；如果您在一份订单里订购了多种商品并且销售方只给您发出了部分商品时，您与销售方之间仅就实际向您发出的商品建立了合同关系。
    </p>
    <p>
        3. 由于市场变化及各种以合理商业努力难以控制的因素的影响，<?= $app_short_name ?>无法保证您提交的订单信息中希望购买的商品都会有货；如您拟购买的商品，发生缺货，您有权取消订单。
    </p>

    <div class="section-title">第八条 配送</div>
    <p>
        1. <?= $app_short_name ?>将会把商品(货物)送到您所指定的收货地址，所有在<?= $app_short_name ?>上列出的送货时间为参考时间，参考时间的计算是根据库存状况、正常的处理过程和送货时间、送货地点的基础上估计得出的。
    </p>
    <p>
        2. 因如下情况造成订单延迟或无法配送等，<?= $app_short_name ?>将无法承担延迟配送的责任：<br>
        (1) 客户提供的信息错误、地址不详细等原因导致的；<br>
        (2) 货物送达后无人签收，导致无法配送或延迟配送的；<br>
        (3) 情势变更因素导致的；<br>
        (4) 不可抗力因素导致的，例如：自然灾害、交通戒严、突发战争等。
    </p>

    <div class="section-title">第九条 责任限制及不承诺担保</div>
    <p>
        1. 除非另有明确的书面说明，<?= $app_short_name ?>及其所包含的或以其它方式通过<?= $app_short_name ?>提供给您的全部信息、内容、材料、产品(包括软件)和服务，均是在“按现状”和“按现有”的基础上提供的。
    </p>
    <p>
        2. 除非另有明确的书面说明，“<?= $app_short_name ?>”不对<?= $app_short_name ?>的运营及其包含在本网站上的信息、内容、材料、产品(包括软件)或服务作任何形式的、明示或默示的声明或担保(根据中华人民共和国法律另有规定的以外)。
    </p>
    <p>
        3. “<?= $app_short_name ?>”不担保<?= $app_short_name ?>所包含的或以其它方式通过<?= $app_short_name ?>提供给您的全部信息、内容、材料、产品(包括软件)和服务、其服务器或从<?= $app_short_name ?>发出的电子信件、信息没有病毒或其他有害成分。
    </p>
    <p>
        4. 如因不可抗力或其它<?= $app_short_name ?>无法控制的原因使<?= $app_short_name ?>销售系统崩溃或无法正常使用导致网上交易无法完成或丢失有关的信息、记录等，“<?= $app_short_name ?>”会合理地尽力协助处理善后事宜。
    </p>

    <div class="section-title">第十条 所有权及知识产权条款</div>
    <p>
        1. 客户一旦接受本协议，即表明该客户主动将其在任何时间段在<?= $app_short_name ?>发表的任何形式的信息内容(包括但不限于客户评价、客户咨询、各类话题文章等信息内容)的财产性权利，如著作权财产权(包括并不限于：复制权、发行权、出租权、展览权、表演权、放映权、广播权、信息网络传播权、摄制权、改编权、翻译权、汇编权以及应当由著作权人享有的其他可转让权利)，全部独家且不可撤销地转让给“<?= $app_short_name ?>”所有，客户同意“<?= $app_short_name ?>”有权就任何主体侵权而单独提起诉讼。
    </p>
    <p>
        2. 本协议已经构成《中华人民共和国著作权法》相关法律规定的著作财产权转让书面协议，其效力及于客户在“<?= $app_short_name ?>”上发布的任何受著作权法保护的作品内容，无论该等内容形成于本协议订立前还是本协议订立后。
    </p>
    <p>
        3. 客户同意并已充分了解本协议的条款，承诺不将已发表于<?= $app_short_name ?>的信息，以任何形式发布或授权其它主体以任何方式使用(包括但限于在各类网站、媒体上使用)。
    </p>
    <p>
        4. “<?= $app_short_name ?>”是<?= $app_short_name ?>的制作者，拥有此网站内容及资源的著作权等合法权利，受国家法律保护，有权不时地对本协议及<?= $app_short_name ?>的内容进行修改，并在<?= $app_short_name ?>张贴，无须另行通知客户。在法律允许的最大限度范围内，“<?= $app_short_name ?>”对本协议及<?= $app_short_name ?>内容拥有解释权。
    </p>
    <p>
        5. 除法律另有强制性规定外，未经“<?= $app_short_name ?>”明确的特别书面许可，任何单位或个人不得以任何方式非法地全部或部分复制、转载、引用、链接、抓取或以其他方式使用<?= $app_short_name ?>的信息内容，否则，“<?= $app_short_name ?>”有权追究其法律责任。
    </p>
    <p>
        6. <?= $app_short_name ?>所刊登的资料信息(诸如文字、图表、标识、按钮图标、图像、声音文件片段、数字下载、数据编辑和软件)，均是“<?= $app_short_name ?>”或其内容提供者的财产，受中国和国际版权法的保护。<?= $app_short_name ?>上所有内容的汇编是“<?= $app_short_name ?>”的排他财产，受中国和国际版权法的保护。<?= $app_short_name ?>上所有软件都是“<?= $app_short_name ?>”或其关联公司或其软件供应商的财产，受中国和国际版权法的保护。
    </p>

    <div class="section-title">第十一条 协议更新及客户关注义务</div>
    <p>
        1. “<?= $app_short_name ?>”尊重客户和消费者的合法权利，本协议及本网站上发布的各类规则、声明等其他内容，均是为了更好的、更加便利的为客户和消费者提供服务。同时<?= $app_short_name ?>也会根据国家法律法规变化、网站运营需要及客户建议适时修改本协议及<?= $app_short_name ?>上的各类规则。
    </p>
    <p>
        2. 协议修改后一旦被张贴在<?= $app_short_name ?>上即生效，并代替原来的协议。客户可随时登录查阅最新协议；客户有义务不时关注并阅读最新版的协议及网站公告。如客户不同意更新后的协议，可以且应立即停止接受<?= $app_short_name ?>网站依据本协议提供的服务；如客户继续使用本网站提供的服务的，即视为同意更新后的协议。<?= $app_short_name ?>建议您在使用<?= $app_short_name ?>之前阅读本协议及<?= $app_short_name ?>的公告。
    </p>
    <p>
        如果本协议中任何一条被视为废止、无效或因任何理由不可执行，该条应视为可分的且并不影响任何其余条款的有效性和可执行性。
    </p>

    <div class="dev-info-box">
        <p style="margin-bottom: 10px; font-weight: bold; font-size: 14px;">开发者信息公示：</p>
        <ul style="list-style: none; padding-left: 0; margin: 0;">
            <li><strong>开发者名称：</strong> <?= $company ?></li>
            <li><strong>应用名称：</strong> <?= $app_name ?></li>
            <?php if(!empty($address)): ?>
                <li><strong>注册/联系地址：</strong> <?= $address ?></li>
            <?php endif; ?>
            <?php if(!empty($tel)): ?>
                <li><strong>联系电话：</strong> <a href="tel:<?= $tel ?>" style="color: #333; text-decoration: none;"><?= $tel ?></a></li>
            <?php endif; ?>
        </ul>
    </div>

    <br><br><br>
</div>

</body>

</html>