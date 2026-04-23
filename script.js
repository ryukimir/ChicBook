const header = document.getElementById("main-header");
const navicon = document.getElementById("navicon");
const navCenter = document.querySelector(".nav-center");
let lastScrollY = window.scrollY;

window.addEventListener("scroll", () => {
  if (lastScrollY < window.scrollY && window.scrollY > 80) {
    header.classList.add("nav-hidden");
  } else {
    header.classList.remove("nav-hidden");
  }
  lastScrollY = window.scrollY;
});

navicon.addEventListener("click", () => {
  navCenter.classList.toggle("mobile-active");
});

const interactiveElements = document.querySelectorAll(
  ".tag, .btn-post, .btn-auth",
);

interactiveElements.forEach((item) => {
  item.addEventListener("click", (e) => {
    console.log("Action");
  });
});
