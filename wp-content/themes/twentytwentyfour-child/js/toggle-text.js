document.addEventListener("DOMContentLoaded", () => {
  const blocks = document.querySelectorAll(".toggle-text-block");

  blocks.forEach((block) => {
    const hidden = block.querySelector(".toggle-text-hidden");
    const btn = block.querySelector(".toggle-button");
    if (!hidden || !btn) return;

    const setState = (expanded) => {
      hidden.classList.toggle("expanded", expanded);
      btn.setAttribute("aria-expanded", expanded ? "true" : "false");
      btn.textContent = expanded ? "−" : "+";
    };

    // Default: stängd
    setState(false);

    btn.addEventListener("click", () => {
      const expanded = hidden.classList.contains("expanded");
      setState(!expanded);
    });
  });
});