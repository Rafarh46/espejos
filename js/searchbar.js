const search = document.getElementById("search-toggle");
const items = document.querySelectorAll(".product-menu li");

search.addEventListener("input", () => {
  const value = search.value.toLowerCase();

  items.forEach(item => {
    item.style.display =
      item.textContent.toLowerCase().includes(value)
        ? "block"
        : "none";
  });
});F