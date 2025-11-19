/*
=================================================
--- ⭐️ NEW PARTICLE ANIMATION SCRIPT ⭐️ ---
=================================================
*/
document.addEventListener("DOMContentLoaded", () => {
  const canvas = document.getElementById("particle-canvas");
  if (!canvas) return; // Don't run if canvas isn't here

  const ctx = canvas.getContext("2d");
  let width, height, particles, mouse;

  // Particle class
  class Particle {
    constructor() {
      this.x = Math.random() * width;
      this.y = Math.random() * height;
      this.vx = Math.random() * 0.8 - 0.4; // x velocity
      this.vy = Math.random() * 0.8 - 0.4; // y velocity
      this.radius = Math.random() * 1.5 + 1;
      this.opacity = Math.random() * 0.5 + 0.2;
    }

    draw() {
      ctx.beginPath();
      ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(255, 255, 255, ${this.opacity})`;
      ctx.fill();
    }

    update() {
      // Move particle
      this.x += this.vx;
      this.y += this.vy;

      // Handle screen edges
      if (this.x < 0 || this.x > width) this.vx = -this.vx;
      if (this.y < 0 || this.y > height) this.vy = -this.vy;

      // Mouse repel
      if (mouse.x && mouse.y) {
        const dx = this.x - mouse.x;
        const dy = this.y - mouse.y;
        const distance = Math.sqrt(dx * dx + dy * dy);

        if (distance < mouse.radius) {
          const forceDirectionX = dx / distance;
          const forceDirectionY = dy / distance;
          const force = (mouse.radius - distance) / mouse.radius;

          this.x += forceDirectionX * force * 1.5;
          this.y += forceDirectionY * force * 1.5;
        }
      }
    }
  }

  // Initialization
  function init() {
    // --- ⭐️ FIX: Size canvas to the full window ---
    width = canvas.width = window.innerWidth;
    height = canvas.height = window.innerHeight;
    // --- End of Fix ---

    particles = [];
    mouse = { x: null, y: null, radius: 150 }; // Increased mouse radius

    // Create particles based on screen size
    const particleCount = Math.floor((width * height) / 10000);
    for (let i = 0; i < particleCount; i++) {
      particles.push(new Particle());
    }
  }

  // Animation loop
  function animate() {
    ctx.clearRect(0, 0, width, height);
    particles.forEach((p) => {
      p.update();
      p.draw();
    });
    requestAnimationFrame(animate);
  }

  // Event Listeners
  window.addEventListener("resize", init);

  // --- ⭐️ FIX: Get mouse position relative to the window ---
  window.addEventListener("mousemove", (e) => {
    mouse.x = e.clientX;
    mouse.y = e.clientY;
  });
  window.addEventListener("mouseout", () => {
    mouse.x = null;
    mouse.y = null;
  });
  // --- End of Fix ---

  init();
  animate();
});
