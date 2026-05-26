(() => {
  const buttons = Array.from(document.querySelectorAll("[data-filter]"));
  const cards = Array.from(document.querySelectorAll(".case-card[data-tags]"));

  if (!buttons.length || !cards.length) {
    return;
  }

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const filter = button.dataset.filter || "all";

      buttons.forEach((item) => item.classList.toggle("is-active", item === button));
      cards.forEach((card) => {
        const tags = (card.dataset.tags || "").trim().split(/\s+/);
        card.classList.toggle("is-hidden", filter !== "all" && !tags.includes(filter));
      });
    });
  });
})();
