<?php
/**
 * 免责声明同意书 - 烬熵Cinder
 * 用户确认免责声明后记录到 SQLite 数据库
 */

// === 配置 ===
define('DISCLAIMER_DB', dirname(__DIR__) . '/data/disclaimer.db');
define('DISCLAIMER_VERSION', 'v4.0');
$admin_token = 'cinder2026'; // 管理面板密码，请修改此值

// ========== 处理提交 ==========
$error_msg = '';
$success_msg = '';
$result = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $qq = trim($_POST["qq"] ?? "");
    $device_fp = trim($_POST["device_fp"] ?? "");

    if (!preg_match("/^[1-9]\d{4,10}$/", $qq)) {
        $error_msg = '请输入有效的QQ号。';
        $result = 'error';
    } else {
        if (!$device_fp) {
            $device_fp = "web_" . time();
        }
        $ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? ($_SERVER["HTTP_X_FORWARDED_FOR"] ?? ($_SERVER["HTTP_X_REAL_IP"] ?? ($_SERVER["REMOTE_ADDR"] ?? "0.0.0.0")));
        if (strpos($ip, ",") !== false) $ip = trim(explode(",", $ip)[0]);

        try {
            // 自动初始化数据库
            initDatabase();

            $db = new PDO("sqlite:" . DISCLAIMER_DB, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $db->exec("PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;");

            foreach (["qq" => $qq, "ip_address" => $ip, "device_fp" => $device_fp] as $field => $val) {
                $st = $db->prepare("SELECT COUNT(*) as cnt FROM disclaimer_consents WHERE $field = ?");
                $st->execute([$val]);
                if ($st->fetch()["cnt"] > 0) {
                    $error_msg = '您已同意，无法添加新记录';
                    $result = 'error';
                    break;
                }
            }

            if ($result !== 'error') {
                $st = $db->prepare("INSERT INTO disclaimer_consents (qq, ip_address, device_fp, version) VALUES (?, ?, ?, ?)");
                $st->execute([$qq, $ip, $device_fp, DISCLAIMER_VERSION]);
                $success_msg = '✅ 同意成功！现在可以正常使用机器人了。';
                $result = 'success';
            }
        } catch (PDOException $e) {
            $error_msg = (strpos($e->getMessage(), "UNIQUE") !== false)
                ? '您已同意，无法添加新记录'
                : '服务器繁忙，请稍后再试。';
            $result = 'error';
        }
    }
}
$form_hidden = ($result === 'success') ? ' style="display:none"' : '';

// ========== 管理面板 ==========
$is_admin = false;
$admin_msg = '';
$admin_users = [];
if (!empty($_GET['admin']) && $_GET['admin'] === $admin_token) {
    $is_admin = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
        try {
            $adb = new PDO("sqlite:" . DISCLAIMER_DB, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $adb->exec("PRAGMA journal_mode=WAL");
            if ($_POST['admin_action'] === 'delete' && !empty($_POST['admin_qq'])) {
                $st = $adb->prepare("DELETE FROM disclaimer_consents WHERE qq = ?");
                $st->execute([$_POST['admin_qq']]);
                $admin_msg = '已删除 QQ: ' . htmlspecialchars($_POST['admin_qq']) . ' (' . $st->rowCount() . ' 条)';
            }
        } catch (PDOException $e) {
            $admin_msg = '数据库错误: ' . $e->getMessage();
        }
    }

    try {
        $adb = new PDO("sqlite:" . DISCLAIMER_DB, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $adb->exec("PRAGMA journal_mode=WAL");
        $st = $adb->query("SELECT id, qq, ip_address, device_fp, version, created_at FROM disclaimer_consents ORDER BY id DESC LIMIT 200");
        $admin_users = $st->fetchAll();
    } catch (PDOException $e) {}
}

/**
 * 自动初始化数据库（如果不存在）
 */
function initDatabase(): void {
    if (file_exists(DISCLAIMER_DB)) return;
    $dir = dirname(DISCLAIMER_DB);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $db = new PDO("sqlite:" . DISCLAIMER_DB, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $db->exec("PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;");
    $db->exec("
        CREATE TABLE IF NOT EXISTS disclaimer_consents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            qq TEXT NOT NULL,
            ip_address TEXT NOT NULL DEFAULT '',
            device_fp TEXT NOT NULL DEFAULT '',
            version TEXT NOT NULL DEFAULT 'v4.0',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_disclaimer_qq ON disclaimer_consents(qq);
        CREATE INDEX IF NOT EXISTS idx_disclaimer_ip ON disclaimer_consents(ip_address);
        CREATE INDEX IF NOT EXISTS idx_disclaimer_fp ON disclaimer_consents(device_fp);
    ");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>免责声明同意书 - 烬熵Cinder</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
<h1>免责声明同意书<span class="vb"><?=DISCLAIMER_VERSION?></span></h1>
<p class="st">更新日期：2026年5月16日</p>

<?php if ($error_msg): ?>
<div class="alert alert-e"><?=htmlspecialchars($error_msg)?></div>
<?php endif; ?>

<?php if ($success_msg): ?>
<div class="alert alert-s"><?=htmlspecialchars($success_msg)?></div>
<?php endif; ?>

<!-- ========== 1 ========== -->
<div class="card">
<h2><span class="sn">1</span>人工智能生成内容声明</h2>
<p>烬熵Cinder（以下简称"本机器人"）的所有对话回复、文本生成、图像处理、代码生成及其他内容输出功能，均基于<strong>第三方大型语言模型（LLM）及人工智能技术自动生成</strong>。开发者仅提供技术接入、消息转发及群管理功能等中介性技术服务。</p>
<p><strong>开发者明确声明：不对大模型输出的任何内容承担任何形式的直接或间接责任。</strong>无论该等输出是否包含事实错误、偏见言论、违法信息、侵权内容或任何其他不当表述，开发者均不为此承担责任。</p>
<div class="legal-box">
<strong>📌 法律依据：</strong>依据《生成式人工智能服务管理暂行办法》（2023年8月15日施行）第九条，生成式人工智能服务提供者应当依法承担网络信息内容生产者责任。本机器人作为技术接入层，底层模型由第三方独立提供并运行，开发者已在合理范围内履行标识、过滤等合规义务，但不对模型生成内容承担实质性审核与担保责任。
</div>
<h3 style="margin-top:14px;color:#8B5E3C;font-size:0.9rem">用户须特别注意以下事项</h3>
<ul>
<li><strong>不保证准确性：</strong>AI生成内容可能存在事实性错误、逻辑矛盾、计算偏差或过时信息，用户不应将其作为决策的唯一依据。</li>
<li><strong>不反映开发者立场：</strong>AI生成内容不代表开发者、运营者或任何关联方的观点、立场、政治倾向或价值观。</li>
<li><strong>不构成专业建议：</strong>AI生成内容不得被视为法律、医疗、金融、投资、心理或其他任何领域的专业意见。涉及上述领域的重大决策，请务必咨询具备相应资质的专业人士。</li>
<li><strong>可能包含偏见：</strong>由于训练数据的局限性，模型输出可能包含基于性别、种族、地域、宗教等方面的隐性偏见。</li>
<li><strong>幻觉风险：</strong>大语言模型存在"幻觉"（Hallucination）现象，可能生成看似合理但实际错误或虚构的信息，包括但不限于编造引用来源、虚构事实、杜撰数据等。</li>
</ul>
</div>

<!-- ========== 2 ========== -->
<div class="card">
<h2><span class="sn">2</span>内容免责条款</h2>
<ul>
<li><strong>准确性免责：</strong>AI生成的信息可能存在事实性错误、过时信息或逻辑偏差。开发者不保证任何输出内容的准确性、完整性、可靠性、时效性或适用性。用户应独立核实关键信息。</li>
<li><strong>合法性免责：</strong>用户利用本机器人生成的内容，其合法性由用户自行承担全部责任。若用户利用本机器人生成、传播违反中华人民共和国法律法规的内容，用户应独立承担相应的行政、民事或刑事法律责任。</li>
<li><strong>侵权责任免责：</strong>AI生成内容可能无意识地引用、复述或改编受著作权保护的作品。对于由此可能引发的著作权、商标权、专利权等知识产权纠纷，开发者不承担责任。如权利人认为AI输出内容侵犯其合法权益，请直接联系底层模型服务提供商。</li>
<li><strong>决策风险免责：</strong>因采纳、依赖或使用AI输出内容而导致的任何直接或间接损失（包括但不限于财产损失、商业机会损失、名誉损害、数据丢失、诉讼费用等），开发者概不负责。</li>
<li><strong>信息传播免责：</strong>用户将通过本机器人获取的信息传播至第三方场景（包括但不限于群聊转发、社交媒体发布、公开引用等），由此产生的任何争议、索赔或法律责任，均由用户自行承担。</li>
</ul>
</div>

<!-- ========== 3 ========== -->
<div class="card">
<h2><span class="sn">3</span>用户行为规范与禁止事项</h2>
<p>使用本机器人即表示您承诺遵守以下规范。如开发者发现或合理怀疑存在违规行为，开发者有权在不事先通知的情况下暂停或永久终止您对本机器人的使用权限。</p>
<ul>
<li><strong>禁止违法违规内容：</strong>不得利用本机器人生成、传播、存储任何违反《中华人民共和国刑法》《网络安全法》《数据安全法》《个人信息保护法》《反电信网络诈骗法》等法律法规的内容，包括但不限于危害国家安全、煽动颠覆政权、恐怖主义、极端主义、分裂主义、淫秽色情、赌博、暴力、凶杀、恐怖信息及教唆犯罪等内容。</li>
<li><strong>禁止网络攻击与入侵：</strong>不得利用本机器人进行或尝试进行黑客攻击、分布式拒绝服务攻击（DDoS）、端口扫描、病毒传播、漏洞探测、逆向工程、爬取底层API接口等危害网络安全的任何行为。</li>
<li><strong>禁止垃圾信息与骚扰：</strong>不得利用本机器人生成或传播垃圾广告、营销信息、钓鱼链接、恶意软件、未经授权的商业推广内容，或利用本机器人对其他用户进行骚扰、威胁、侮辱、诽谤、跟踪等侵犯他人合法权益的行为。</li>
<li><strong>禁止提示词注入与越狱攻击：</strong>不得试图通过提示词注入（Prompt Injection）、越狱提示（Jailbreak Prompt）、角色扮演诱导、多轮诱导等方式绕过本机器人的安全限制，诱导模型生成违反法律法规或安全策略的内容。此类行为一经发现，开发者有权永久封禁该用户的使用权限。</li>
<li><strong>禁止批量自动化调用：</strong>未经开发者书面允许，不得利用脚本、爬虫、自动化工具等对本机器人进行批量、高频的非正常调用，不得试图提取、复制、抓取本机器人的回复内容用于训练同类模型或其他商业目的。</li>
<li><strong>禁止冒充与仿冒：</strong>不得冒用开发者、腾讯公司或其他第三方实体的名义使用本机器人进行任何活动；不得利用AI生成的回复伪装为真人或其他机器人产品。</li>
<li><strong>禁止侵犯未成年人权益：</strong>不得利用本机器人向未成年人传播不适宜其接触的内容；不得诱导未成年人进行转账、充值、提供个人信息等行为。</li>
<li><strong>禁止数据爬取与模型提取：</strong>不得试图通过任何技术手段提取、复制、转储底层大语言模型的参数、权重、架构等非公开信息，不得对本机器人进行任何形式的逆向工程。</li>
</ul>
</div>

<!-- ========== 4 ========== -->
<div class="card">
<h2><span class="sn">4</span>群管理功能责任限制</h2>
<p>本机器人提供的群管理功能（包括但不限于自动加群审批、关键词过滤、违规消息检测、自动禁言、欢迎消息、定时消息等）仅为技术辅助工具。开发者特别声明：</p>
<ul>
<li><strong>误判免责：</strong>群管理功能基于规则或AI判断，不可避免地存在误判、漏判的可能。对于因误判导致的误封、误踢、误删消息等操作失误，开发者不承担任何责任。</li>
<li><strong>操作失误免责：</strong>群管理功能可能在特定情况下（如消息并发高峰、网络延迟、API限流等）出现操作延迟、重复操作或操作失败，开发者不对因此产生的任何后果负责。</li>
<li><strong>数据丢失免责：</strong>群管理功能涉及的消息记录、群成员数据、配置信息等，开发者会尽力保障数据安全，但不排除因硬件故障、软件错误、第三方服务中断等原因导致的数据丢失。管理员应对关键配置进行本地备份。</li>
<li><strong>服务中断免责：</strong>因第三方API服务（如QQ开放平台接口、底层大模型API）波动、限流、升级或下线而导致的本机器人群管理功能不可用，开发者不承担责任。</li>
<li><strong>群内纠纷免责：</strong>本机器人群管理功能的自动化操作（如禁言、踢出等）不构成对群内纠纷的实质性裁决。群内成员之间的任何纠纷由群主/管理员自行协调处理，开发者不介入、不评判、不担责。</li>
</ul>
</div>

<!-- ========== 5 ========== -->
<div class="card">
<h2><span class="sn">5</span>第三方依赖与服务</h2>
<p>本机器人的运行严重依赖于以下第三方服务。任何第三方服务的变更、中断、关闭均可能导致本机器人全部或部分功能不可用：</p>
<ul>
<li>QQ开放平台（包括但不限于QQ频道/群机器人API、消息推送服务）</li>
<li>底层第三方大语言模型API服务提供商（包括但不限于模型推理接口、内容安全过滤接口等）</li>
<li>服务器托管服务提供商</li>
<li>其他可能涉及的内容安全、数据存储等第三方服务</li>
</ul>
<p>开发者<strong>不对任何第三方服务的内容、可用性、安全性、稳定性、合规性</strong>承担任何形式的保证或责任。因第三方服务导致的任何直接或间接损失，用户应直接向该第三方追责。</p>
<div class="legal-box">
<strong>📌</strong> 依据《生成式人工智能服务管理暂行办法》第十四条，服务提供者发现违法内容应当及时处置。开发者已部署内容安全过滤机制，但不能保证100%拦截所有违规内容，且内容合规的最终责任需结合底层模型服务提供者的责任共同界定。
</div>
</div>

<!-- ========== 6 ========== -->
<div class="card">
<h2><span class="sn">6</span>服务可用性与保证免责</h2>
<ul>
<li><strong>"现状"提供：</strong>本机器人按"现状"（AS-IS）和"可用"（AVAILABLE）基础提供，不附带任何明示或暗示的保证。</li>
<li><strong>无SLA保障：</strong>本服务为免费/非商业性质的技术演示及社区服务项目，不提供任何形式服务水平协议（SLA）。开发者不保证服务不会中断、及时、安全或无错误。</li>
<li><strong>无商业承诺：</strong>本服务可能随时因技术维护、成本控制、政策变动、第三方接口变更等原因暂停或终止，开发者无需提前通知用户。</li>
<li><strong>不可抗力：</strong>因自然灾害、战争、恐怖活动、政府行为、电力中断、网络骨干故障、大规模DDoS攻击等不可抗力因素导致的服务中断，开发者不承担任何责任。</li>
<li><strong>维护通知：</strong>开发者会尽力在计划维护前通过公告渠道通知用户，但紧急维护可能无法提前通知，敬请谅解。</li>
</ul>
</div>

<!-- ========== 7 ========== -->
<div class="card">
<h2><span class="sn">7</span>隐私保护与数据处理</h2>
<p>开发者承诺严格遵守《中华人民共和国个人信息保护法》《数据安全法》《网络安全法》及《生成式人工智能服务管理暂行办法》等相关法律法规对个人信息保护的要求。</p>
<ul>
<li><strong>收集范围：</strong>为提供服务和保障安全，本机器人可能会记录以下信息：用户的QQ号、群组ID、发送的消息内容、发送时间戳、IP地址、设备指纹信息。</li>
<li><strong>对话记录处理：</strong>用户的对话记录将被发送至第三方大语言模型API以获取AI回复。该过程受第三方模型服务提供商的隐私政策约束。开发者建议用户不要在对话中主动披露个人敏感信息（如身份证号、银行卡号、家庭住址、登录密码等）。</li>
<li><strong>数据用途：</strong>收集的数据仅用于提供对话服务、安全审计、异常行为检测、服务质量改进和纠纷排查，不会用于任何其他目的。</li>
<li><strong>数据共享限制：</strong>开发者不会将用户数据出售、出租或交易给任何第三方。除以下情形外，开发者不会向任何第三方提供用户数据：（1）法律法规或有权机关明确要求；（2）为保护用户或公众的重大合法权益所必需；（3）用户书面同意。</li>
<li><strong>数据保留期限：</strong>用户对话记录将根据服务运营需要保留合理期限，超过保留期限后将自动删除或匿名化处理。</li>
<li><strong>用户权利：</strong>依据《个人信息保护法》，用户有权查阅、更正、删除其个人信息，有权撤回同意。如需行使上述权利，可以联系<strong>QQ：1449783068</strong>。</li>
<li><strong>数据安全措施：</strong>开发者已采取合理的技术和组织安全措施保护用户数据，包括但不限于数据库加密、访问控制、日志审计等。</li>
<li><strong>重要提示：</strong>QQ号及发送的消息内容将被发送至第三方大模型，<strong>请不要在对话中输入任何敏感个人信息、商业机密、涉密信息</strong>。因用户主动输入敏感信息导致的隐私泄露风险，由用户自行承担。</li>
</ul>
</div>

<!-- ========== 8 ========== -->
<div class="card">
<h2><span class="sn">8</span>知识产权</h2>
<ul>
<li><strong>软件代码：</strong>本机器人的软件代码、界面设计、系统架构、文档等知识产权归开发者所有，未经开发者书面许可，任何人不得复制、修改、分发、反向工程或用于商业目的。</li>
<li><strong>底层模型：</strong>本机器人调用的大语言模型的知识产权归其各自开发方所有。开发者不对底层模型的训练数据、算法、参数等主张任何权利。</li>
<li><strong>对话内容：</strong>用户输入的提示词内容知识产权归用户所有。AI生成的回复内容因由第三方模型自动生成，根据现行法律，其著作权归属尚存争议，开发者对此不主张知识产权。但用户不得以任何方式主张AI生成内容的原创性或独占性著作权。</li>
<li><strong>商标标识：</strong>"烬熵Cinder"名称及相关标识的知识产权归开发者所有，未经授权不得使用。</li>
<li><strong>侵权投诉：</strong>如权利人认为本机器人展示或生成的内容侵犯了您的知识产权，请提供权属证明及侵权材料至<strong>QQ：1449783068</strong>，开发者将在核实后依法处理。</li>
</ul>
</div>

<!-- ========== 9 ========== -->
<div class="card">
<h2><span class="sn">9</span>未成年人保护</h2>
<p>依据《未成年人保护法》第七十六条及《生成式人工智能服务管理暂行办法》第十六条，本机器人特别声明：</p>
<ul>
<li><strong>监护人责任：</strong>未成年人在使用本机器人前应征得其父母或监护人的同意。监护人应对未成年人的使用行为承担监督责任。</li>
<li><strong>内容提醒：</strong>AI生成内容可能包含不适宜未成年人阅读或接触的信息类型。建议监护人对未成年人使用本机器人进行合理限制和引导。</li>
<li><strong>禁止诱导：</strong>开发者严禁利用本机器人以任何形式诱导未成年人提供个人信息、进行转账、充值或从事其他可能损害未成年人权益的活动。</li>
<li><strong>主动发现：</strong>如开发者发现或收到举报称本机器人被用于向未成年人传播不适当内容，将立即采取措施制止并视情况向有关部门报告。</li>
</ul>
</div>

<!-- ========== 10 ========== -->
<div class="card">
<h2><span class="sn">10</span>法律依据</h2>
<p>本免责声明遵循以下法律法规及规范性文件制定：</p>
<div class="legal-box">
① 《生成式人工智能服务管理暂行办法》（2023年8月15日施行）<br>
② 《人工智能生成合成内容标识办法》（2025年9月1日施行）<br>
③ 《中华人民共和国网络安全法》（2017年6月1日施行）<br>
④ 《中华人民共和国数据安全法》（2021年9月1日施行）<br>
⑤ 《中华人民共和国个人信息保护法》（2021年11月1日施行）<br>
⑥ 《互联网信息服务算法推荐管理规定》（2022年3月1日施行）<br>
⑦ 《互联网信息服务深度合成管理规定》（2023年1月10日施行）<br>
⑧ 《中华人民共和国未成年人保护法》（2021年6月1日修订施行）<br>
⑨ 《中华人民共和国著作权法》（2021年6月1日施行）<br>
⑩ 《中华人民共和国刑法》及司法解释中涉及计算机信息系统安全、网络犯罪相关条款<br>
⑪ QQ机器人开发者运营规范及相关协议
</div>
<p style="margin-top:12px;font-size:0.82rem;color:#6B6B6B">特别提示：依据《生成式人工智能服务管理暂行办法》第二十二条，本办法仅适用于向境内公众提供生成式AI服务的场景。本机器人已依据《互联网信息服务算法推荐管理规定》《互联网信息服务深度合成管理规定》等法规履行合规义务，但鉴于技术接入层的定位，开发者不对底层模型的训练数据、算法逻辑及生成内容的全面合规性承担实质性审查义务。</p>
</div>

<!-- ========== 11 ========== -->
<div class="card">
<h2><span class="sn">11</span>争议解决与管辖法律</h2>
<p>本免责声明的解释、效力及争议解决适用<strong>中华人民共和国法律</strong>。</p>
<p>因本声明引起的或与本声明相关的任何争议，双方应首先通过友好协商解决。协商不成的，任何一方均可向<strong>服务器所在地有管辖权的人民法院</strong>提起诉讼。</p>
<p>如果本声明的任何条款被有管辖权的法院认定为无效或不可执行，该条款应在必要的最低限度内被修改或删除，其余条款继续完全有效。</p>
</div>

<!-- ========== 12 ========== -->
<div class="card">
<h2><span class="sn">12</span>声明的修改与更新</h2>
<ul>
<li>开发者保留根据法律法规变化、服务内容调整或运营需要随时修改本免责声明的权利。</li>
<li>修改后的声明将在本页面发布后即行生效，代替原有声明。重大修改将在版本号（当前为<?=DISCLAIMER_VERSION?>）中体现。</li>
<li>用户继续使用本机器人即视为接受修改后的声明。如用户不同意修改内容，应立即停止使用本机器人。</li>
<li>建议用户定期查阅本页面以了解最新版本的免责声明。</li>
<li>如对本免责声明有任何疑问、意见或投诉，请联系<strong>QQ：1449783068</strong>。</li>
</ul>
</div>

<!-- ========== 提交表单 ========== -->
<div class="box"<?=$form_hidden?>>
<form method="post" onsubmit="document.querySelector('.btn-s').disabled=true">
<p style="font-size:0.85rem;color:#4B4B4B;margin-bottom:14px">我已仔细阅读并理解上述免责声明全部内容，同意受其约束。</p>
<label>
<input type="checkbox" id="agree" required onchange="document.querySelector('.btn-s').disabled=!this.checked">
<span>我同意上述免责声明</span>
</label>
<div class="fg">
<label for="qq">请输入您的 QQ 号</label>
<input type="text" id="qq" name="qq" placeholder="请输入QQ号" required pattern="[1-9]\d{4,10}" title="请输入有效的QQ号">
</div>
<button type="submit" class="btn-s" disabled>提交同意</button>
</form>
</div>

<p class="ft">烬熵Cinder · <?=DISCLAIMER_VERSION?></p>

<?php if ($is_admin): ?>
<!-- ========== 管理面板 ========== -->
<div class="admin-section">
<h3>🔧 管理面板</h3>
<?php if ($admin_msg): ?><div class="alert alert-s"><?=htmlspecialchars($admin_msg)?></div><?php endif; ?>
<?php if (!empty($admin_users)): ?>
<table class="admin-tbl">
<tr><th>ID</th><th>QQ</th><th>IP</th><th>版本</th><th>时间</th><th>操作</th></tr>
<?php foreach ($admin_users as $u): ?>
<tr>
<td><?=htmlspecialchars($u['id'])?></td>
<td><?=htmlspecialchars($u['qq'])?></td>
<td><?=htmlspecialchars($u['ip_address'])?></td>
<td><?=htmlspecialchars($u['version'])?></td>
<td><?=htmlspecialchars($u['created_at'])?></td>
<td>
<form method="post" style="display:inline" onsubmit="return confirm('确定删除?')">
<input type="hidden" name="admin_action" value="delete">
<input type="hidden" name="admin_qq" value="<?=htmlspecialchars($u['qq'])?>">
<button type="submit" class="btn-del">删除</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p style="color:#6B6B6B;font-size:0.85rem">暂无记录</p>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$is_admin): ?>
<div class="admin-login"><a href="?admin=<?=urlencode($admin_token)?>">管理面板</a></div>
<?php endif; ?>

</div>
</body>
</html>
