
function toggleChat() {
  const chat = document.getElementById("chatPopup");
  chat.classList.toggle("open");
}

let PRODUCTS = [];
let PRODUCT_MAP = {};

async function loadProducts(){
  const res = await fetch("products.json");
  PRODUCTS = await res.json();
  PRODUCTS.forEach(p=>{
    PRODUCT_MAP[p.name.toLowerCase()] = p;
    PRODUCT_MAP[p.title.toLowerCase()] = p;
  });
}

function enhanceProducts(element){
  let html = element.innerHTML;

  PRODUCTS.forEach(p=>{
    const names = [p.name, p.title];

    names.forEach(n=>{
      const reg = new RegExp(`(${n})`, "gi");
      html = html.replace(reg,
        `<span class="product-hover" data-product="${p.id}">$1</span>`
      );
    });
  });

  element.innerHTML = html;
}

function showCard(productId){
  const p = PRODUCTS.find(x=>x.id==productId);
  if(!p) return;

  const card = document.getElementById("productCard");

  card.innerHTML = `
    <div class="card-title">${p.name}</div>
    <div class="card-sub">${p.title}</div>
    <div><b>Price:</b> BDT ${p.price_bdt}</div>
    <div><b>Sizes:</b> ${p.sizes.join(", ")}</div>
    <div><b>Colors:</b> ${p.colors.join(", ")}</div>
    <div><b>Stock:</b> ${p.stock}</div>
  `;

  card.style.display="block";
}

function hideCard(){
  document.getElementById("productCard").style.display="none";
}

document.addEventListener("mouseover",e=>{
  if(e.target.classList.contains("product-hover")){
    showCard(e.target.dataset.product);
  }
});

document.addEventListener("mouseout",e=>{
  if(e.target.classList.contains("product-hover")){
    hideCard();
  }
});

async function sendMessage() {

  const msgEl = document.getElementById("msg");
  const chatBox = document.getElementById("chat-box");
  const msg = msgEl.value.trim();

  if (!msg) return;

  chatBox.innerHTML += `<div class="bubble user">${msg}</div>`;

  const typingId = "typing-" + Date.now();

  chatBox.innerHTML += `<div class="bubble bot" id="${typingId}">Typing...</div>`;

  chatBox.scrollTop = chatBox.scrollHeight;

  msgEl.value = "";

  try {

    const res = await fetch("api.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: new URLSearchParams({ message: msg })
    });

    const data = await res.json();

    document.getElementById(typingId)?.remove();

    const bubble = document.createElement("div");
    bubble.className = "bubble bot";
    bubble.innerHTML = data.reply;

    chatBox.appendChild(bubble);

    enhanceProducts(bubble);

    chatBox.scrollTop = chatBox.scrollHeight;

  } catch (e) {

    document.getElementById(typingId)?.remove();

    chatBox.innerHTML += `<div class="bubble bot">❌ Cannot reach API</div>`;

    chatBox.scrollTop = chatBox.scrollHeight;
  }
}

window.onload = loadProducts;
