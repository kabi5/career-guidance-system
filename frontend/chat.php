<?php
require_once 'config/db.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Career Assistant · Career Compass</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
  <a href="dashboard.php" class="back">← Dashboard</a>
  <span>Career Assistant</span>
</header>
<main class="container">
  <h1>Ask the career assistant</h1>
  <p class="muted">Ask about careers, subjects, qualifications or programmes at Lesotho institutions. This is guidance, not a final decision.</p>

  <section class="card chat">
    <div id="chat-log" class="chat-log">
      <div class="msg bot">Hi! What would you like to know about your future career?</div>
    </div>

    <div class="chips">
      <button type="button" class="chip">What can I study to become a nurse?</button>
      <button type="button" class="chip">What does Botho University offer?</button>
      <button type="button" class="chip">What is RIASEC?</button>
      <button type="button" class="chip">How do I get a bursary?</button>
    </div>

    <form id="chat-form" class="chat-form" autocomplete="off">
      <input type="text" id="chat-input" maxlength="500" placeholder="Type your question…" required>
      <button type="submit" class="btn primary">Send</button>
    </form>
  </section>
</main>

<script>
const log   = document.getElementById('chat-log');
const form  = document.getElementById('chat-form');
const input = document.getElementById('chat-input');

function addMsg(text, who) {
  const div = document.createElement('div');
  div.className = 'msg ' + who;
  div.textContent = text;          // textContent: safe against HTML injection
  log.appendChild(div);
  log.scrollTop = log.scrollHeight;
  return div;
}

async function ask(text) {
  addMsg(text, 'user');
  const wait = addMsg('Thinking…', 'bot');
  try {
    const res  = await fetch('chat_api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({message: text})
    });
    const data = await res.json();
    wait.textContent = data.reply || 'Sorry, something went wrong.';
  } catch (e) {
    wait.textContent = 'Could not reach the assistant. Is the AI engine running?';
  }
  log.scrollTop = log.scrollHeight;
}

form.addEventListener('submit', e => {
  e.preventDefault();
  const text = input.value.trim();
  if (!text) return;
  input.value = '';
  ask(text);
});

document.querySelectorAll('.chip').forEach(c =>
  c.addEventListener('click', () => ask(c.textContent)));
</script>
</body>
</html>