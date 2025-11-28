<?php
/**
 * Authentication Controller
 */

declare(strict_types=1);

class AuthController extends BaseController
{
    private User $userModel;
    private Validator $validator;

    public function __construct()
    {
        $this->userModel = new User();
        $this->validator = new Validator();
    }

    /**
     * Show login form
     */
    public function showLogin(): void
    {
        // Redirect if already logged in
        if (Auth::check()) {
            $this->redirect('/pages/dashboard.php');
            return;
        }

        $this->view('auth/login', [
            'errors' => [],
            'success' => ''
        ]);
    }

    /**
     * Handle login
     */
    public function login(): void
    {
        if (!Helper::isPost()) {
            $this->redirect('/pages/login.php');
            return;
        }

        $email = trim(Helper::post('email', ''));
        $password = Helper::post('password', '');

        $errors = [];

        if (empty($email) || !Validator::isValidEmail($email)) {
            $errors[] = "ایمیل معتبر وارد کنید.";
        }
        if (empty($password)) {
            $errors[] = "رمز عبور را وارد کنید.";
        }

        if (empty($errors)) {
            $user = $this->userModel->findByEmail($email);
            
            if ($user && $this->userModel->verifyPassword($password, $user['password'])) {
                Auth::login($user['id'], $user);
                $this->redirect('/pages/dashboard.php');
                return;
            } else {
                $errors[] = "ایمیل یا رمز عبور اشتباه است.";
            }
        }

        $this->view('auth/login', [
            'errors' => $errors,
            'success' => ''
        ]);
    }

    /**
     * Show register form
     */
    public function showRegister(): void
    {
        if (Auth::check()) {
            $this->redirect('/pages/dashboard.php');
            return;
        }

        $this->view('auth/register', [
            'errors' => [],
            'success' => ''
        ]);
    }

    /**
     * Handle registration
     */
    public function register(): void
    {
        if (!Helper::isPost()) {
            $this->redirect('/pages/register.php');
            return;
        }

        $data = [
            'full_name' => trim(Helper::post('full_name', '')),
            'email' => trim(Helper::post('email', '')),
            'password' => Helper::post('password', ''),
            'password_confirm' => Helper::post('password_confirm', ''),
            'usertype' => Helper::post('usertype', ''),
            'gender' => Helper::post('gender', ''),
            'city' => trim(Helper::post('city', '')),
            'province' => Helper::post('province', ''),
            'phone' => trim(Helper::post('phone', '')),
            'national_code' => trim(Helper::post('national_code', ''))
        ];

        $errors = [];

        // Validation
        if (empty($data['full_name'])) $errors[] = "نام و نام خانوادگی را وارد کنید.";
        if (empty($data['email']) || !Validator::isValidEmail($data['email'])) {
            $errors[] = "ایمیل معتبر وارد کنید.";
        }
        if (empty($data['password']) || strlen($data['password']) < 6) {
            $errors[] = "رمز عبور باید حداقل 6 کاراکتر باشد.";
        }
        if ($data['password'] !== $data['password_confirm']) {
            $errors[] = "رمز عبور و تکرار آن یکسان نیستند.";
        }
        if (!in_array($data['usertype'], ['هم‌خانه', 'صاحب‌خانه'])) {
            $errors[] = "نوع کاربر را انتخاب کنید.";
        }
        if (!in_array($data['gender'], ['آقا', 'خانم'])) {
            $errors[] = "جنسیت را انتخاب کنید.";
        }
        if (empty($data['city'])) $errors[] = "شهر را وارد کنید.";
        if (empty($data['province'])) $errors[] = "استان را انتخاب کنید.";
        if (!empty($data['phone']) && !Validator::isValidPhone($data['phone'])) {
            $errors[] = "شماره تماس معتبر نیست.";
        }

        // Check duplicates
        if (empty($errors)) {
            if ($this->userModel->emailExists($data['email'])) {
                $errors[] = "این ایمیل قبلاً ثبت شده است.";
            }
            if (!empty($data['phone']) && $this->userModel->phoneExists($data['phone'])) {
                $errors[] = "این شماره تماس قبلاً ثبت شده است.";
            }
        }

        if (empty($errors)) {
            try {
                $userId = $this->userModel->create([
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'password' => $this->userModel->hashPassword($data['password']),
                    'usertype' => $data['usertype'],
                    'gender' => $data['gender'],
                    'city' => $data['city'],
                    'province' => $data['province'],
                    'phone' => $data['phone'] ?: null,
                    'national_code' => $data['national_code'] ?: null
                ]);

                Auth::login($userId);
                $this->redirect('/pages/dashboard.php');
                return;
            } catch (Exception $e) {
                $errors[] = "خطا در ثبت‌نام. لطفاً دوباره تلاش کنید.";
            }
        }

        $this->view('auth/register', [
            'errors' => $errors,
            'success' => ''
        ]);
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/');
    }
}

