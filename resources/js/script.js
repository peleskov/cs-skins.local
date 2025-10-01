/*-----------------------------------------------------------------------------------

    Template Name:zomo - Online Food Delivery
    Template URI: https://themes.pixelstrap.net/zomo
    Description: This is Food Ordering Html Template
    Author: Pixelstrap
    Author URL: https://themeforest.net/user/pixelstrap

----------------------------------------------------------------------------------- */

// 01.wishlist js
// 02.Ratio js
// 03.tap to top js
// 04.Range js
// 05.Plus Minus Item  Js
// 06.popup Quantity Js
// 07.RTL js
// 08.Dark js
// 09.Menu sidebar js

/*=====================
  01. wishlist added start
==========================*/
const divs = document.querySelectorAll(".like-btn");
divs.forEach((el) =>
  el.addEventListener("click", (event) => {
    event.target.parentNode.classList.toggle("animate");
    event.target.parentNode.classList.toggle("active");
    event.target.parentNode.classList.toggle("inactive");
  })
);

/*====================
  02. Ratio js
=======================*/
window.addEventListener("load", () => {
  const bgImg = document.querySelectorAll(".bg-img");
  for (i = 0; i < bgImg.length; i++) {
    let bgImgEl = bgImg[i];

    if (bgImgEl.classList.contains("bg-top")) {
      bgImgEl.parentNode.classList.add("b-top");
    } else if (bgImgEl.classList.contains("bg-bottom")) {
      bgImgEl.parentNode.classList.add("b-bottom");
    } else if (bgImgEl.classList.contains("bg-center")) {
      bgImgEl.parentNode.classList.add("b-center");
    } else if (bgImgEl.classList.contains("bg-left")) {
      bgImgEl.parentNode.classList.add("b-left");
    } else if (bgImgEl.classList.contains("bg-right")) {
      bgImgEl.parentNode.classList.add("b-right");
    }

    if (bgImgEl.classList.contains("blur-up")) {
      bgImgEl.parentNode.classList.add("blur-up", "lazyload");
    }

    if (bgImgEl.classList.contains("bg_size_content")) {
      bgImgEl.parentNode.classList.add("b_size_content");
    }

    bgImgEl.parentNode.classList.add("bg-size");
    const bgSrc = bgImgEl.src;
    bgImgEl.style.display = "none";
    bgImgEl.parentNode.setAttribute(
      "style",
      `
      background-image: url(${bgSrc});
      background-size:cover;
      background-position: center;
      background-repeat: no-repeat;
      display: block;
      `
    );
  }
});

/*====================
  03. tap to top js
=======================*/
const btn = document.querySelector(".scroll");

if (btn) {
  btn.addEventListener("click", function () {
    scroll(0, 200);
  });

  window.onscroll = function showHide() {
    if (
      document.body.scrollTop > 500 ||
      document.documentElement.scrollTop > 500
    ) {
      btn.style.transform = "scale(1)";
    } else {
      btn.style.transform = "scale(0)";
    }
  };
}

function scroll(target, duration) {
  if (duration <= 0) {
    return;
  }
  let difference = target - document.documentElement.scrollTop;
  let speed = (difference / duration) * 10;
  setTimeout(function () {
    document.documentElement.scrollTop += speed;
    if (document.documentElement.scrollTop == target) {
      return;
    }
    scroll(target, duration - 10);
  }, 10);
}

/*====================
  04. Range js
=======================*/
const rangeInputs = document.querySelectorAll('input[type="range"]');
const numberInput = document.querySelector('input[type="number"]');

function handleInputChange(e) {
  let target = e.target;
  if (e.target.type !== "range") {
    target = document.getElementById("range");
  }
  if (target) {
    const min = target.min;
    const max = target.max;
    const val = target.value;

    target.style.backgroundSize = ((val - min) * 100) / (max - min) + "%100%";
  }
}

rangeInputs.forEach((input) => {
  input.addEventListener("input", handleInputChange);
});

/*====================
  05. Plus Minus Quantity Item js
=======================*/
const plusMinus = document.querySelectorAll(".plus-minus");

for (var i = 0; i < plusMinus.length; ++i) {
  const addButton = plusMinus[i].querySelector(".add");
  const subButton = plusMinus[i].querySelector(".sub");
  addButton?.addEventListener("click", function () {
    const inputEl = this.parentNode.querySelector("input[type='number']");
    if (inputEl.value < 10) {
      inputEl.value = Number(inputEl.value) + 1;
    }
  });
  subButton?.addEventListener("click", function () {
    const inputEl = this.parentNode.querySelector("input[type='number']");
    if (inputEl.value >= 1) {
      inputEl.value = Number(inputEl.value) - 1;
    }
  });
}

/*======================
  06. popup Quantity Item js
// =======================*/
document.addEventListener("DOMContentLoaded", function () {
  var faqContainers = document.getElementsByClassName("add-btn");
  var faqToggle = document.getElementsByClassName("plus-minus")[0];

  if (faqToggle) {
    for (var i = 0; i < faqContainers.length; i++) {
      faqContainers[i].addEventListener("click", function () {
        if (faqToggle.classList.contains("d-flex")) {
          faqToggle.classList.remove("d-flex");
        } else {
          faqToggle.classList.add("d-flex");
        }
      });
    }
  }
});

document.addEventListener("DOMContentLoaded", function () {
  var faqContainers = document.getElementsByClassName("apply-btn");
  var faqToggle = document.getElementsByClassName("cart-popup")[0];

  if (faqToggle) {
    for (var i = 0; i < faqContainers.length; i++) {
      faqContainers[i].addEventListener("click", function () {
        if (faqToggle.classList.contains("d-flex")) {
          faqToggle.classList.remove("d-flex");
        } else {
          faqToggle.classList.add("d-flex");
        }
      });
    }
  }
});

document.addEventListener("DOMContentLoaded", function () {
  var faqContainers = document.getElementsByClassName("cart-btn");
  var faqToggle = document.getElementsByClassName("pay-btn")[0];

  if (faqToggle) {
    for (var i = 0; i < faqContainers.length; i++) {
      faqContainers[i].addEventListener("click", function () {
        if (faqToggle.classList.contains("d-flex")) {
          faqToggle.classList.remove("d-flex");
          faqToggle.classList.remove("d-block");
        } else {
          faqToggle.classList.add("d-flex");
        }
      });
    }
  }
});



/*====================
  09. Menu sidebar
======================*/
// Bootstrap 5 offcanvas работает автоматически через data-bs-toggle и data-bs-dismiss атрибуты