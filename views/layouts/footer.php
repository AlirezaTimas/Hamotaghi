    </main>
    
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="mb-1">© 2025 هم‌اتاقی — همه حقوق محفوظ است.</p>
            <small>طراحی و توسعه با ❤️</small>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?= Helper::e($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

