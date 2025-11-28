    </div> <!-- End container -->

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script>
    <?php if (isset($showParticles) && $showParticles): ?>
    particlesJS("particles-js", {
        particles: {
            number: { value: 80, density: { enable: true, value_area: 1000 } },
            color: { value: ["#ffab00", "#8b5cf6", "#4caf50", "#2196f3"] },
            shape: { type: ["circle", "triangle"] },
            opacity: { value: 0.3, random: true, anim: { enable: true, speed: 1, opacity_min: 0.1 } },
            size: { value: 3, random: true, anim: { enable: true, speed: 2, size_min: 1 } },
            line_linked: { enable: true, distance: 120, color: "#ffab00", opacity: 0.2, width: 1 },
            move: { enable: true, speed: 1.5, direction: "none", random: true, straight: false, out_mode: "bounce" }
        },
        interactivity: {
            detect_on: "canvas",
            events: { onhover: { enable: true, mode: "repulse" }, onclick: { enable: true, mode: "push" }, resize: true },
            modes: { repulse: { distance: 100, duration: 0.4 }, push: { particles_nb: 4 } }
        },
        retina_detect: true
    });
    <?php endif; ?>
    </script>
    <?php if (isset($additionalScripts)): ?>
        <script><?php echo $additionalScripts; ?></script>
    <?php endif; ?>
</body>
</html>

