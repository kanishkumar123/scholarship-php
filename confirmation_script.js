// --- Interactive JavaScript (Unchanged) ---
const headerTitle = document.getElementById("firework-trigger");
const downloadBtn = document.getElementById("download-btn");
const chimeAudio = document.getElementById("chime-audio");
let fireworkIsLaunched = false;

// Array of vibrant colors for the firework and confetti
const FIREWORK_COLORS = [
  "#FF69B4", // Vibrant Pink
  "#007bff", // Electric Blue
  "#32CD32", // Lime Green
  "#FFA500", // Orange
  "#9400D3", // Dark Violet
  "#FFD700", // Gold
];

// --- Audio Cue Logic ---
function playChime() {
  if (chimeAudio) {
    chimeAudio.volume = 0.5;
    // Reset and play for immediate effect
    chimeAudio.currentTime = 0;
    chimeAudio.play().catch((error) => {
      console.warn(
        "Audio playback prevented by browser. Check file path/format.",
        error
      );
    });
  }
}

// --- Confetti Burst Logic ---
function createConfettiBurst(buttonElement) {
  const rect = buttonElement.getBoundingClientRect();
  const btnCenterX = rect.left + rect.width / 2;
  const btnCenterY = rect.top + rect.height / 2;
  const confettiCount = 30;

  for (let i = 0; i < confettiCount; i++) {
    const confetti = document.createElement("div");
    confetti.classList.add("confetti");

    const color =
      FIREWORK_COLORS[Math.floor(Math.random() * FIREWORK_COLORS.length)];
    confetti.style.setProperty("--confetti-color", color);

    // Position confetti in the center of the button, then animate outwards
    const initialX = btnCenterX + (Math.random() - 0.5) * 5;
    const initialY = btnCenterY + (Math.random() - 0.5) * 5;

    confetti.style.left = `${initialX}px`;
    confetti.style.top = `${initialY}px`;
    confetti.style.opacity = "1";

    document.body.appendChild(confetti);

    // Physics for confetti (toss up and spin)
    const angle = Math.random() * Math.PI;
    const velocity = 8 + Math.random() * 8;
    const vx = velocity * 5 * Math.cos(angle); // Increased distance multiplier
    const vy = velocity * 5 * Math.sin(angle) * -1;

    const duration = 1.5 + Math.random() * 1;

    confetti.animate(
      [
        { transform: "translate(0, 0) rotate(0deg)", opacity: 1 },
        {
          transform: `translate(${vx}px, ${vy}px) rotate(${
            Math.random() > 0.5 ? 720 : -720
          }deg)`,
          opacity: 0,
        },
      ],
      {
        duration: duration * 1000,
        easing: "cubic-bezier(0.1, 1, 0.4, 1)",
      }
    ).onfinish = () => confetti.remove();
  }
}

// Attach Confetti and Chime to the Download button
downloadBtn.addEventListener("click", (e) => {
  // We DON'T prevent default here because the link needs to navigate
  createConfettiBurst(downloadBtn);
  playChime();
  // A small delay to ensure the confetti animation starts before navigating
  setTimeout(() => {
    // Continue the default link action (navigation/download)
    window.location.href = downloadBtn.href;
  }, 100);
});

// --- Background Particle Stream Setup ---
function setupBackgroundParticles() {
  const particleCount = 15;
  for (let i = 0; i < particleCount; i++) {
    const particle = document.createElement("div");
    particle.classList.add("background-particle");

    particle.style.left = `${Math.random() * 100}vw`;

    particle.style.animationDelay = `${Math.random() * 15}s`;
    particle.style.animationDuration = `${12 + Math.random() * 6}s`;

    document.body.appendChild(particle);
  }
}
setupBackgroundParticles();

// --- FIREWORK LOGIC (Optimized) ---
const FIREWORK_GLOBALS = {
  gravity: 0.08,
  friction: 0.98,
  totalLifeDuration: 4000,
  initialVelocityRange: { min: 8, max: 12 },
};

function createPalmTreeExplosion(centerX, centerY, explosionColor) {
  const { gravity, friction, totalLifeDuration, initialVelocityRange } =
    FIREWORK_GLOBALS;
  const particleCount = 50;
  let localMainParticles = [];

  for (let i = 0; i < particleCount; i++) {
    const particle = document.createElement("div");
    particle.classList.add("firework-particle");

    particle.style.backgroundColor = explosionColor;
    particle.style.color = explosionColor;

    const initialX = centerX + (Math.random() - 0.5) * 4;
    const initialY = centerY + (Math.random() - 0.5) * 4;
    particle.style.left = `${initialX}px`;
    particle.style.top = `${initialY}px`;
    document.body.appendChild(particle);

    const angle = Math.random() * 2 * Math.PI;
    const velocity =
      initialVelocityRange.min +
      Math.random() * (initialVelocityRange.max - initialVelocityRange.min);

    localMainParticles.push({
      element: particle,
      x: initialX,
      y: initialY,
      vx: velocity * Math.cos(angle),
      vy: velocity * Math.sin(angle),
      life: 1,
      initialDelay: Math.random() * 50,
    });
  }

  let startTime = performance.now();
  let lastTime = performance.now();
  let animationFrameId;

  function animateParticles(currentTime) {
    const deltaTime = (currentTime - lastTime) / (1000 / 60);
    lastTime = currentTime;

    for (let i = localMainParticles.length - 1; i >= 0; i--) {
      const p = localMainParticles[i];

      if (currentTime - startTime < p.initialDelay) {
        continue;
      }

      p.vy += gravity * deltaTime;
      p.vx *= friction;
      p.vy *= friction;

      p.x += p.vx * deltaTime;
      p.y += p.vy * deltaTime;

      const elapsed = currentTime - startTime;
      p.life = 1 - elapsed / totalLifeDuration;
      if (p.life < 0) p.life = 0;

      p.element.style.transform = `translate(${p.x - centerX}px, ${
        p.y - centerY
      }px)`;
      p.element.style.opacity = p.life;

      if (p.life <= 0.01) {
        p.element.remove();
        localMainParticles.splice(i, 1);
      }
    }

    if (localMainParticles.length > 0) {
      animationFrameId = requestAnimationFrame(animateParticles);
    } else {
      cancelAnimationFrame(animationFrameId);
    }
  }

  animationFrameId = requestAnimationFrame(animateParticles);
}

function launchSingleShell(delay, colorIndex) {
  const shell = document.createElement("div");
  shell.classList.add("firework-shot");

  const horizontalOffset = Math.random() * 40 - 20;
  shell.style.left = `calc(50% + ${horizontalOffset}px)`;

  const targetHeight = window.innerHeight * (0.8 + Math.random() * 0.1);
  document.documentElement.style.setProperty(
    "--firework-height",
    `-${targetHeight}px`
  );

  const explosionColor = FIREWORK_COLORS[colorIndex % FIREWORK_COLORS.length];
  shell.style.setProperty("--shell-color", explosionColor);
  shell.style.animationDelay = `${delay}ms`;

  document.body.appendChild(shell);

  shell.addEventListener(
    "animationend",
    () => {
      const rect = shell.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2;
      const centerY = rect.top + rect.height / 2;

      createPalmTreeExplosion(centerX, centerY, explosionColor);

      shell.remove();
    },
    { once: true }
  );
}

function triggerGrandFirework() {
  if (fireworkIsLaunched) return;
  fireworkIsLaunched = true;

  // Play sound when firework is triggered (needs user gesture)
  playChime();

  const BURST_COUNT = 4;
  const INTERVAL_MS = 350;

  for (let i = 0; i < BURST_COUNT; i++) {
    const delay = i * INTERVAL_MS;
    const colorIndex = i;

    setTimeout(() => {
      launchSingleShell(0, colorIndex);
    }, delay);
  }

  setTimeout(() => {
    fireworkIsLaunched = false;
  }, BURST_COUNT * INTERVAL_MS + 4500);
}

// Attach the firework launch function to the header title
headerTitle.addEventListener("click", triggerGrandFirework);

// 2. Removed the lag-inducing general document click ripple effect.

// 3. Spotlight Hover Effect (Unchanged)
const mainCard = document.getElementById("main-card");
const spotlight = mainCard.querySelector(".spotlight");

mainCard.addEventListener("mousemove", (e) => {
  const rect = mainCard.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const y = e.clientY - rect.top;

  spotlight.style.setProperty("--x", `${(x / rect.width) * 100}%`);
  spotlight.style.setProperty("--y", `${(y / rect.height) * 100}%`);
});
