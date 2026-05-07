(function () {
  const chatPanel = document.querySelector("[data-chat-endpoint]");
  if (!chatPanel) {
    return;
  }

  const form = document.getElementById("chat-form");
  const input = document.getElementById("chat-input");
  const messages = document.getElementById("chat-messages");
  const csrf = chatPanel.dataset.csrfToken || "";
  const endpoint = chatPanel.dataset.chatEndpoint;

  function addMessage(text, type) {
    const bubble = document.createElement("div");
    bubble.className = `chat-bubble ${type}`;
    bubble.textContent = text;
    messages.appendChild(bubble);
    messages.scrollTop = messages.scrollHeight;
    return bubble;
  }

  async function sendMessage(text) {
    addMessage(text, "user");
    const pending = addMessage("Dang tra cuu...", "assistant pending");

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrf,
        },
        body: JSON.stringify({ message: text }),
      });
      const data = await response.json();
      pending.classList.remove("pending");
      pending.textContent = data.ok ? data.answer : data.message || "Khong the tra loi luc nay.";
      if (data.ok && data.used_ai === false) {
        pending.classList.add("fallback");
      }
    } catch (error) {
      pending.classList.remove("pending");
      pending.classList.add("fallback");
      pending.textContent = "Khong ket noi duoc may chu chatbot. Vui long thu lai.";
    }
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    const text = input.value.trim();
    if (!text) {
      return;
    }
    input.value = "";
    sendMessage(text);
  });

  document.querySelectorAll("[data-prompt]").forEach((button) => {
    button.addEventListener("click", () => {
      input.value = button.dataset.prompt || "";
      input.focus();
    });
  });
})();
