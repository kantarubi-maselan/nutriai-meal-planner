<?php
require_once 'includes/config.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();
$name   = $_SESSION['user_name'] ?? 'there';

// ── Load user profile for AI context ─────────────────────────
$stmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id=?");
$stmt->execute([$userId]);
$profile = $stmt->fetch() ?: [];

// ── Load active plan summary ──────────────────────────────────
$stmt = $db->prepare("
    SELECT title, goal, diet_type, target_calories
    FROM meal_plans WHERE user_id=? AND is_active=1
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$userId]);
$activePlan = $stmt->fetch();

// ── Load chat history (last 40 messages) ─────────────────────
$stmt = $db->prepare("
    SELECT role, message, created_at
    FROM chat_history
    WHERE user_id=?
    ORDER BY created_at DESC LIMIT 40
");
$stmt->execute([$userId]);
$history = array_reverse($stmt->fetchAll());

// ── Handle clear history ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_history'])) {
    $db->prepare("DELETE FROM chat_history WHERE user_id=?")->execute([$userId]);
    header('Location: chat.php'); exit;
}

$pageTitle  = 'AI Chat Assistant';
$activePage = 'chat';
require 'includes/header.php';
?>

<style>
/* ══════════════════════════════════════════════════════════
   NutriAI Chat — Warm Organic Pastel Theme
   ══════════════════════════════════════════════════════════ */

/* ── Chat page wrapper ────────────────────────────────────── */
.chat-page {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 1.5rem;
  height: calc(100vh - 4rem);
  max-height: 860px;
}

/* ── Sidebar panel ────────────────────────────────────────── */
.chat-sidebar {
  display: flex; flex-direction: column; gap: 1rem;
  overflow-y: auto;
}

.chat-profile-card {
  background: linear-gradient(145deg, var(--sage-light) 0%, var(--lav-light) 100%);
  border-radius: var(--radius-md); padding: 1.5rem; text-align: center;
  border: 1px solid var(--border); position: relative; overflow: hidden;
}
.chat-profile-card::before {
  content: ''; position: absolute; width: 120px; height: 120px; border-radius: 50%;
  background: rgba(255,255,255,.25); top: -40px; right: -30px;
}
.chat-avatar {
  width: 56px; height: 56px; border-radius: 50%; margin: 0 auto .75rem;
  background: linear-gradient(135deg, var(--sage-dark), var(--lavender));
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; font-weight: 700; color: var(--white);
  font-family: var(--font-display);
  box-shadow: 0 4px 16px rgba(107,158,98,.3);
}
.chat-profile-name  { font-family: var(--font-display); font-size: 1rem; font-weight: 700; }
.chat-profile-plan  { font-size: .75rem; color: var(--text-mid); margin-top: .25rem; }
.chat-profile-badge {
  display: inline-flex; align-items: center; gap: .3rem;
  background: rgba(255,255,255,.6); padding: .25rem .75rem; border-radius: 50px;
  font-size: .72rem; font-weight: 600; color: var(--sage-dark); margin-top: .6rem;
}

/* ── Quick prompts ────────────────────────────────────────── */
.quick-section-title {
  font-size: .72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--text-soft); margin-bottom: .6rem;
}
.quick-prompts { display: flex; flex-direction: column; gap: .4rem; }
.quick-btn {
  width: 100%; text-align: left; padding: .65rem .85rem;
  background: var(--white); border: 1.5px solid var(--border);
  border-radius: var(--radius-sm); cursor: pointer;
  font-size: .82rem; color: var(--text-mid); font-family: var(--font-body);
  transition: var(--transition); line-height: 1.4;
}
.quick-btn:hover {
  border-color: var(--sage); background: var(--sage-light);
  color: var(--sage-dark); transform: translateX(3px);
}
.quick-btn-icon { margin-right: .4rem; }

/* ── Chat tips ────────────────────────────────────────────── */
.chat-tips {
  background: var(--white); border: 1px solid var(--border);
  border-radius: var(--radius-md); padding: 1.1rem;
}
.chat-tips ul { list-style: none; display: flex; flex-direction: column; gap: .4rem; }
.chat-tips li { font-size: .78rem; color: var(--text-soft); display: flex; gap: .4rem; }

/* ── Main chat panel ──────────────────────────────────────── */
.chat-main {
  display: flex; flex-direction: column;
  background: var(--white); border-radius: var(--radius-lg);
  border: 1px solid var(--border); overflow: hidden;
  box-shadow: var(--shadow-sm);
}

/* ── Chat header ──────────────────────────────────────────── */
.chat-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--cream) 0%, var(--white) 100%);
  flex-shrink: 0;
}
.chat-header-left { display: flex; align-items: center; gap: .85rem; }
.chat-bot-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, var(--sage-dark), var(--lavender));
  display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
  box-shadow: 0 3px 12px rgba(107,158,98,.25);
  flex-shrink: 0;
}
.chat-bot-name   { font-weight: 700; font-size: .95rem; font-family: var(--font-display); }
.chat-bot-status { font-size: .75rem; color: var(--sage-dark); display: flex; align-items: center; gap: .3rem; }
.status-dot {
  width: 7px; height: 7px; border-radius: 50%; background: var(--sage-dark);
  animation: pulse-dot 2s ease infinite;
}
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ── Messages area ────────────────────────────────────────── */
.chat-messages {
  flex: 1; overflow-y: auto; padding: 1.5rem;
  display: flex; flex-direction: column; gap: 1.25rem;
  scroll-behavior: smooth;
  background: var(--cream);
}
.chat-messages::-webkit-scrollbar { width: 5px; }
.chat-messages::-webkit-scrollbar-track { background: transparent; }
.chat-messages::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

/* ── Message rows ─────────────────────────────────────────── */
.msg-row {
  display: flex; gap: .75rem; align-items: flex-end;
  animation: msgIn .3s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes msgIn { from { opacity:0; transform: translateY(10px) scale(.97); } to { opacity:1; transform: none; } }

.msg-row.user  { flex-direction: row-reverse; }
.msg-row.assistant { flex-direction: row; }

.msg-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .85rem; flex-shrink: 0; margin-bottom: 2px;
}
.msg-avatar.bot-av {
  background: linear-gradient(135deg, var(--sage-dark), var(--lavender));
  color: var(--white);
}
.msg-avatar.user-av {
  background: linear-gradient(135deg, var(--peach-light), var(--rose-light));
  color: var(--text-dark); font-weight: 700; font-family: var(--font-display);
}

.msg-bubble {
  max-width: 72%; padding: .85rem 1.1rem;
  border-radius: 18px; font-size: .9rem; line-height: 1.65;
  position: relative; word-break: break-word;
}
.msg-row.user .msg-bubble {
  background: var(--sage-dark); color: var(--white);
  border-bottom-right-radius: 4px;
  box-shadow: 0 3px 12px rgba(107,158,98,.25);
}
.msg-row.assistant .msg-bubble {
  background: var(--white); color: var(--text-dark);
  border-bottom-left-radius: 4px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
}
.msg-bubble p  { margin: 0 0 .5rem; }
.msg-bubble p:last-child { margin: 0; }
.msg-bubble ul, .msg-bubble ol { padding-left: 1.25rem; margin: .4rem 0; }
.msg-bubble li { margin-bottom: .25rem; }
.msg-bubble strong { font-weight: 700; }
.msg-bubble code {
  background: rgba(0,0,0,.07); padding: .1rem .35rem; border-radius: 4px;
  font-size: .85em; font-family: 'Courier New', monospace;
}
.msg-row.user .msg-bubble code { background: rgba(255,255,255,.2); }
.msg-time {
  font-size: .68rem; margin-top: .3rem; opacity: .6;
  text-align: right;
}
.msg-row.assistant .msg-time { text-align: left; }

/* ── Welcome screen ───────────────────────────────────────── */
.chat-welcome {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center; text-align: center;
  padding: 2rem; gap: 1.5rem;
}
.welcome-icon {
  width: 80px; height: 80px; border-radius: 50%;
  background: linear-gradient(135deg, var(--sage-light), var(--lav-light));
  display: flex; align-items: center; justify-content: center; font-size: 2rem;
  box-shadow: var(--shadow-md); animation: float 3s ease-in-out infinite;
}
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
.welcome-title { font-family: var(--font-display); font-size: 1.5rem; font-weight: 700; }
.welcome-sub   { color: var(--text-soft); font-size: .9rem; max-width: 360px; line-height: 1.6; }
.welcome-chips { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; max-width: 480px; }
.welcome-chip  {
  padding: .45rem 1rem; border-radius: 50px; font-size: .82rem; font-weight: 500;
  background: var(--white); border: 1.5px solid var(--border); cursor: pointer;
  transition: var(--transition); color: var(--text-mid);
}
.welcome-chip:hover { border-color: var(--sage); background: var(--sage-light); color: var(--sage-dark); }

/* ── Typing indicator ─────────────────────────────────────── */
.typing-indicator {
  display: none; align-items: flex-end; gap: .75rem;
}
.typing-indicator.show { display: flex; }
.typing-bubble {
  background: var(--white); border: 1px solid var(--border);
  border-radius: 18px; border-bottom-left-radius: 4px;
  padding: .85rem 1.1rem; display: flex; gap: 4px; align-items: center;
  box-shadow: var(--shadow-sm);
}
.typing-dot {
  width: 7px; height: 7px; border-radius: 50%; background: var(--sage);
  animation: typing-bounce 1.2s ease infinite;
}
.typing-dot:nth-child(2) { animation-delay: .2s; }
.typing-dot:nth-child(3) { animation-delay: .4s; }
@keyframes typing-bounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-6px)} }

/* ── Input area ───────────────────────────────────────────── */
.chat-input-area {
  padding: 1rem 1.25rem; border-top: 1px solid var(--border);
  background: var(--white); flex-shrink: 0;
}
.chat-input-wrap {
  display: flex; gap: .75rem; align-items: flex-end;
  background: var(--cream); border: 1.5px solid var(--border);
  border-radius: 16px; padding: .6rem .75rem;
  transition: var(--transition);
}
.chat-input-wrap:focus-within {
  border-color: var(--sage); background: var(--white);
  box-shadow: 0 0 0 3px rgba(168,197,160,.2);
}
#chatInput {
  flex: 1; border: none; background: transparent; resize: none;
  font-family: var(--font-body); font-size: .92rem; color: var(--text-dark);
  outline: none; line-height: 1.5; max-height: 160px; min-height: 24px;
}
#chatInput::placeholder { color: var(--text-soft); }
.send-btn {
  width: 38px; height: 38px; border-radius: 50%; border: none; flex-shrink: 0;
  background: var(--sage-dark); color: var(--white);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: var(--transition); font-size: 1rem;
}
.send-btn:hover { background: #5a8c52; transform: scale(1.08); }
.send-btn:disabled { background: var(--border); cursor: not-allowed; transform: none; }
.input-hint { font-size: .72rem; color: var(--text-soft); margin-top: .4rem; text-align: center; }

/* ── Suggested follow-ups ─────────────────────────────────── */
.followups {
  display: flex; flex-wrap: wrap; gap: .4rem;
  padding: .75rem 1.25rem 0; flex-shrink: 0;
}
.followup-chip {
  padding: .35rem .85rem; border-radius: 50px; font-size: .78rem; font-weight: 500;
  background: var(--lav-light); border: 1px solid var(--lavender); cursor: pointer;
  color: #7c5cbf; transition: var(--transition);
}
.followup-chip:hover { background: var(--lavender); color: var(--white); }

@media (max-width:900px) {
  .chat-page { grid-template-columns: 1fr; height: auto; }
  .chat-sidebar { display: none; }
  .chat-main { height: calc(100vh - 6rem); }
}
</style>

<!-- ── Page ───────────────────────────────────────────────────── -->
<div class="chat-page">

  <!-- ── Sidebar ─────────────────────────────────────────────── -->
  <div class="chat-sidebar">

    <!-- Profile card -->
    <div class="chat-profile-card">
      <div class="chat-avatar"><?= mb_strtoupper(mb_substr($name,0,1)) ?></div>
      <div class="chat-profile-name"><?= sanitize($name) ?></div>
      <?php if ($activePlan): ?>
      <div class="chat-profile-plan">Active: <?= sanitize($activePlan['title']) ?></div>
      <div class="chat-profile-badge">
        <?php
        $dietEmoji = ['normal'=>'🍽️','halal'=>'🕌','vegetarian'=>'🥦','vegan'=>'🌱','keto'=>'🥑','paleo'=>'🥩'];
        echo ($dietEmoji[$activePlan['diet_type']] ?? '🍽️') . ' ' . ucfirst($activePlan['diet_type']);
        ?>
      </div>
      <?php else: ?>
      <div class="chat-profile-plan" style="margin-top:.35rem">No active plan</div>
      <?php endif; ?>
    </div>

    <!-- Quick prompts -->
    <div class="card" style="padding:1.25rem">
      <div class="quick-section-title">💬 Quick Questions</div>
      <div class="quick-prompts">
        <?php
        $prompts = [
          ['🥗','What should I eat for dinner tonight?'],
          ['💪','High protein meal ideas under 500 calories?'],
          ['🛒','Cheap healthy meals I can make this week?'],
          ['⏱️','Quick 15-minute meal ideas?'],
          ['😴','What foods help improve sleep?'],
          ['🔥','How can I boost my metabolism?'],
          ['🧁','Healthy snack alternatives to junk food?'],
          ['💧','How much water should I drink daily?'],
        ];
        foreach ($prompts as [$icon, $text]): ?>
        <button class="quick-btn" onclick="setPrompt(this.dataset.prompt)" data-prompt="<?= htmlspecialchars($text,ENT_QUOTES) ?>">
          <span class="quick-btn-icon"><?= $icon ?></span><?= $text ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Tips -->
    <div class="chat-tips">
      <div class="quick-section-title">💡 Tips</div>
      <ul>
        <li><span>🎯</span> Be specific — mention your diet type or goal</li>
        <li><span>🛒</span> Tell the AI what ingredients you have</li>
        <li><span>📊</span> Ask about calories or macros for any meal</li>
        <li><span>🔄</span> Ask for alternatives if you dislike a suggestion</li>
      </ul>
    </div>

    <!-- Clear history -->
    <?php if ($history): ?>
    <form method="POST" onsubmit="return confirm('Clear all chat history?')">
      <input type="hidden" name="clear_history">
      <button class="btn btn-ghost btn-sm" style="width:100%;color:var(--text-soft)">
        🗑 Clear Chat History
      </button>
    </form>
    <?php endif; ?>

  </div>

  <!-- ── Main Chat ────────────────────────────────────────────── -->
  <div class="chat-main">

    <!-- Header -->
    <div class="chat-header">
      <div class="chat-header-left">
        <div class="chat-bot-avatar">🤖</div>
        <div>
          <div class="chat-bot-name">NutriAI Assistant</div>
          <div class="chat-bot-status">
            <div class="status-dot"></div>
            Online · Powered by Gemini AI
          </div>
        </div>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center">
        <?php if ($activePlan): ?>
        <span class="badge badge-sage" style="font-size:.72rem">
          🔥 <?= number_format($activePlan['target_calories']) ?> kcal target
        </span>
        <?php endif; ?>
        <a href="generator.php" class="btn btn-primary btn-sm">✨ New Plan</a>
      </div>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">

      <?php if (empty($history)): ?>
      <!-- Welcome screen -->
      <div class="chat-welcome" id="welcomeScreen">
        <div class="welcome-icon">🥗</div>
        <div class="welcome-title">Hi <?= sanitize($name) ?>! I'm NutriAI 👋</div>
        <div class="welcome-sub">
          Your personal AI nutritionist. Ask me anything about food, recipes, meal planning, calories, or health goals.
        </div>
        <div class="welcome-chips" id="welcomeChips">
          <?php
          $chips = [
            "What should I eat for weight loss?",
            "High protein breakfast ideas",
            "Quick dinner under 600 calories",
            "Halal meal prep ideas",
            "Foods that reduce belly fat",
            "Vegetarian protein sources",
          ];
          foreach ($chips as $c): ?>
          <button class="welcome-chip" onclick="setPrompt('<?= htmlspecialchars($c,ENT_QUOTES) ?>')"><?= $c ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <!-- Chat history -->
      <?php foreach ($history as $msg):
        $isUser = $msg['role'] === 'user';
        $time   = date('g:i a', strtotime($msg['created_at']));
        $initial = mb_strtoupper(mb_substr($name,0,1));
      ?>
      <div class="msg-row <?= $msg['role'] ?>">
        <div class="msg-avatar <?= $isUser ? 'user-av' : 'bot-av' ?>">
          <?= $isUser ? $initial : '🤖' ?>
        </div>
        <div>
          <div class="msg-bubble"><?= nl2br(sanitize($msg['message'])) ?></div>
          <div class="msg-time"><?= $time ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <!-- Typing indicator -->
      <div class="typing-indicator" id="typingIndicator">
        <div class="msg-avatar bot-av">🤖</div>
        <div class="typing-bubble">
          <div class="typing-dot"></div>
          <div class="typing-dot"></div>
          <div class="typing-dot"></div>
        </div>
      </div>

    </div>

    <!-- Follow-up chips (shown after AI responds) -->
    <div class="followups" id="followupChips" style="display:none">
      <?php
      $followups = ['Give me a recipe for this','What are the calories?','Suggest an alternative','Add this to my plan','What about for lunch?'];
      foreach ($followups as $f): ?>
      <button class="followup-chip" onclick="setPrompt('<?= htmlspecialchars($f,ENT_QUOTES) ?>')"><?= $f ?></button>
      <?php endforeach; ?>
    </div>

    <!-- Input area -->
    <div class="chat-input-area">
      <div class="chat-input-wrap">
        <textarea
          id="chatInput"
          placeholder="Ask me anything about food, recipes, or nutrition…"
          rows="1"
          maxlength="2000"
        ></textarea>
        <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="Send (Enter)">➤</button>
      </div>
      <div class="input-hint">Press <kbd style="background:var(--border);padding:.1rem .35rem;border-radius:3px;font-size:.7rem">Enter</kbd> to send · <kbd style="background:var(--border);padding:.1rem .35rem;border-radius:3px;font-size:.7rem">Shift+Enter</kbd> for new line</div>
    </div>

  </div>
</div>

<!-- API endpoint -->
<script>
// ── State ───────────────────────────────────────────────────────
const userId   = <?= $userId ?>;
const userName = <?= json_encode($name) ?>;
const userInitial = <?= json_encode(mb_strtoupper(mb_substr($name,0,1))) ?>;
<?php
$profileCtx = '';
if ($profile) {
    $parts = [];
    if ($profile['diet_type'])    $parts[] = "Diet: {$profile['diet_type']}";
    if ($profile['fitness_goal']) $parts[] = "Goal: {$profile['fitness_goal']}";
    if ($profile['weight_kg'])    $parts[] = "Weight: {$profile['weight_kg']}kg";
    if ($profile['daily_calories']) $parts[] = "Calorie target: {$profile['daily_calories']}kcal";
    if ($profile['allergies'])    $parts[] = "Allergies: {$profile['allergies']}";
    $profileCtx = implode(', ', $parts);
}
$planCtx = '';
if ($activePlan) {
    $planCtx = "Active meal plan: {$activePlan['title']} ({$activePlan['diet_type']}, {$activePlan['goal']}, {$activePlan['target_calories']}kcal target).";
}
?>
const SYSTEM_PROMPT = `You are NutriAI, a warm, knowledgeable, and encouraging AI nutritionist. You speak in a friendly, conversational tone — like a personal dietitian who genuinely cares about the user's health.

User profile: <?= addslashes($profileCtx) ?>
<?= addslashes($planCtx) ?>

Guidelines:
- Give practical, actionable nutrition and meal advice
- Always respect the user's diet type (halal, vegetarian, etc.) and allergies
- When suggesting meals, include rough calorie and protein estimates
- Keep responses concise but complete — use short paragraphs and bullet points when listing options
- Occasionally encourage and motivate the user toward their health goals
- If asked for a recipe, include ingredients and brief steps
- Never give medical diagnoses — suggest consulting a doctor for health conditions
- Format responses clearly with emojis sparingly for warmth`;

let conversationHistory = [];
let isLoading = false;

// ── Auto-resize textarea ────────────────────────────────────────
const input = document.getElementById('chatInput');
input.addEventListener('input', () => {
  input.style.height = 'auto';
  input.style.height = Math.min(input.scrollHeight, 160) + 'px';
});
input.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

// ── Set prompt ──────────────────────────────────────────────────
function setPrompt(text) {
  input.value = text;
  input.style.height = 'auto';
  input.style.height = Math.min(input.scrollHeight, 160) + 'px';
  input.focus();
  // Hide welcome screen
  const ws = document.getElementById('welcomeScreen');
  if (ws) ws.style.display = 'none';
}

// ── Add message bubble ──────────────────────────────────────────
function addMessage(role, text, animate = true) {
  const container = document.getElementById('chatMessages');
  const typing    = document.getElementById('typingIndicator');

  const row = document.createElement('div');
  row.className = `msg-row ${role}`;
  if (!animate) row.style.animation = 'none';

  const now = new Date();
  const time = now.toLocaleTimeString('en-US', {hour:'numeric',minute:'2-digit',hour12:true});
  const isUser = role === 'user';

  row.innerHTML = `
    <div class="msg-avatar ${isUser ? 'user-av' : 'bot-av'}">${isUser ? userInitial : '🤖'}</div>
    <div>
      <div class="msg-bubble">${formatMessage(text)}</div>
      <div class="msg-time">${time}</div>
    </div>`;

  container.insertBefore(row, typing);
  scrollToBottom();
}

// ── Format message text (basic markdown) ───────────────────────
function formatMessage(text) {
  return text
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
    .replace(/\*(.*?)\*/g,'<em>$1</em>')
    .replace(/`(.*?)`/g,'<code>$1</code>')
    .replace(/^### (.*$)/gm,'<strong>$1</strong>')
    .replace(/^## (.*$)/gm,'<strong>$1</strong>')
    .replace(/^- (.*$)/gm,'<li>$1</li>')
    .replace(/(<li>.*<\/li>\n?)+/g, s => `<ul>${s}</ul>`)
    .replace(/\n/g,'<br>');
}

// ── Show / hide typing ──────────────────────────────────────────
function showTyping() { document.getElementById('typingIndicator').classList.add('show'); scrollToBottom(); }
function hideTyping()  { document.getElementById('typingIndicator').classList.remove('show'); }

// ── Scroll to bottom ────────────────────────────────────────────
function scrollToBottom() {
  const c = document.getElementById('chatMessages');
  setTimeout(() => c.scrollTop = c.scrollHeight, 50);
}

// ── Main send function ──────────────────────────────────────────
async function sendMessage() {
  const text = input.value.trim();
  if (!text || isLoading) return;

  // Hide welcome screen
  const ws = document.getElementById('welcomeScreen');
  if (ws) ws.remove();

  // Update UI
  input.value = '';
  input.style.height = 'auto';
  isLoading = true;
  document.getElementById('sendBtn').disabled = true;

  // Add user bubble
  addMessage('user', text);

  // Build conversation for API
  conversationHistory.push({ role: 'user', content: text });

  showTyping();

  try {
    // ── Save user message to DB ──────────────────────────────
    await fetch('api/chat-save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ role: 'user', message: text })
    });

    // ── Call Gemini API ──────────────────────────────────────
const response = await fetch('/Ai-Meal-Planner/api/gemini.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    message: text
  })
});

const data = await response.json();
hideTyping();

const reply =
  data.candidates?.[0]?.content?.parts?.[0]?.text ||
  data.error ||
  "No response from AI.";

conversationHistory.push({ role: "assistant", content: reply });

addMessage('assistant', reply);

// Save assistant message
await fetch('api/chat-save.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ role: 'assistant', message: reply })
});

  } catch (err) {
    hideTyping();
    addMessage('assistant', "I'm having trouble connecting right now. Please check your connection and try again. 🌐");
    console.error('Chat error:', err);
  } finally {
    isLoading = false;
    document.getElementById('sendBtn').disabled = false;
    input.focus();
  }
}

// ── Load existing history into conversation array ───────────────
<?php if ($history): ?>
conversationHistory = <?= json_encode(array_map(fn($m) => ['role'=>$m['role'],'content'=>$m['message']], $history)) ?>;
<?php endif; ?>

// ── Auto-scroll to bottom on load ──────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  scrollToBottom();
  input.focus();
});
</script>

<?php require 'includes/footer.php'; ?>