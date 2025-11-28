<?php require __DIR__ . '/../layouts/header.php'; ?>

<div id="particles-js" style="position: fixed; width: 100%; height: 100%; z-index: 0; top: 0; left: 0;"></div>

<div class="container" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; max-width: 420px; z-index: 1;">
    <div class="card" style="background: rgba(20, 20, 35, 0.9); border-radius: 20px; padding: 40px 30px; box-shadow: 0 15px 40px rgba(0,0,0,0.5); backdrop-filter: blur(10px); border: 2px solid #5dade2;">
        <h2 class="mb-4 fw-bold text-center" style="color: #f1c40f;">ورود به هم‌اتاقی</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= Helper::e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= Helper::e($success) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="mb-3">
                <label class="form-label" style="color: #f0f0f0;">ایمیل</label>
                <input type="email" name="email" class="form-control" 
                       style="background: rgba(42,42,60,0.95); border: 1px solid #5dade2; color: #f5f5f5;"
                       placeholder="example@email.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label" style="color: #f0f0f0;">رمز عبور</label>
                <input type="password" name="password" class="form-control"
                       style="background: rgba(42,42,60,0.95); border: 1px solid #5dade2; color: #f5f5f5;"
                       placeholder="رمز عبور خود را وارد کنید" required>
            </div>
            <button type="submit" class="btn btn-primary w-100" 
                    style="background: linear-gradient(135deg,#3498db,#5dade2); border: none; font-weight: 600;">
                ورود
            </button>
        </form>

        <p class="mt-3 text-center" style="color: #e0e0e0;">
            حساب کاربری ندارید؟ 
            <a href="/pages/register.php" style="color: #5dade2; font-weight: 500;">ثبت‌نام کنید</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script>
particlesJS("particles-js", {
  "particles": {
    "number": {"value": 70, "density": {"enable": true, "value_area": 650}},
    "color": {"value": ["#3498db","#5dade2","#f1c40f","#2ecc71"]},
    "shape": {"type": "circle"},
    "opacity": {"value":0.75,"random":true,"anim":{"enable":true,"speed":1,"opacity_min":0.3,"sync":false}},
    "size":{"value":3,"random":true,"anim":{"enable":false}},
    "line_linked":{"enable":true,"distance":120,"color":"#5dade2","opacity":0.6,"width":1},
    "move":{"enable":true,"speed":2,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}
  },
  "interactivity":{
    "detect_on":"canvas",
    "events":{"onhover":{"enable":true,"mode":"grab"},"onclick":{"enable":true,"mode":"push"},"resize":true},
    "modes":{"grab":{"distance":180,"line_linked":{"opacity":0.85}},"push":{"particles_nb":6}}
  },
  "retina_detect": true
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

