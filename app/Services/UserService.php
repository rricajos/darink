<?php

namespace App\Services;

class UserService
{
    protected $session;

    public function __construct()
    {
        $this->session = session();
    }

    public function isLoggedIn(): bool
    {
        return $this->session->get('isLoggedIn') === true;
    }

    public function id(): ?int
    {
        return $this->session->get('user_id');
    }

    public function email(): ?string
    {
        return $this->session->get('user_email');
    }

    public function nickname(): ?string
    {
        return $this->session->get('user_nickname');
    }

    public function setSession(array $user)
    {
        $this->session->set([
            'isLoggedIn' => true,
            'user_id' => $user['user_id'],
            'user_email' => $user['user_email'],
            'user_nickname' => $user['user_nickname'] ?? null,
        ]);
    }

    public function destroy(): void
    {
        $this->session->destroy();
    }

    public function all(): array
    {
        return $this->session->get();
    }

    public function setRedirectAfterLogout(string $url): void
    {
        $this->session->set('redirect_after_logout', $url);
    }

    public function getRedirectAfterLogout(): ?string
    {
        return $this->session->get('redirect_after_logout');
    }

    public function clearRedirectAfterLogout(): void
    {
        $this->session->remove('redirect_after_logout');
    }

}
