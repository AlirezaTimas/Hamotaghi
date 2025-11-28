<?php
/**
 * Base Controller
 */

declare(strict_types=1);

abstract class BaseController
{
    protected array $data = [];
    protected string $viewPath = '';

    /**
     * Render view
     */
    protected function view(string $view, array $data = []): void
    {
        $this->data = array_merge($this->data, $data);
        extract($this->data);
        
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }
        
        require $viewFile;
    }

    /**
     * Return JSON response
     */
    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect
     */
    protected function redirect(string $url, int $code = 302): void
    {
        Helper::redirect($url, $code);
    }

    /**
     * Require authentication
     */
    protected function requireAuth(string $redirectTo = '/pages/login.php'): void
    {
        Auth::requireAuth($redirectTo);
    }

    /**
     * Require role
     */
    protected function requireRole(string $role, string $redirectTo = '/pages/dashboard.php'): void
    {
        Auth::requireRole($role, $redirectTo);
    }
}

