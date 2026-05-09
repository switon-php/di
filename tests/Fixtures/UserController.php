<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class UserController
{
    #[Autowired]
    public UserService $userService;

    #[Autowired]
    public RequestInterface $request;

    #[Autowired]
    public ResponseInterface $response;

    public function createAction(): string
    {
        $body = $this->request->getBody();
        $user = $this->userService->createUser(
            $body['name'] ?? '',
            $body['email'] ?? ''
        );

        return $this->response->json([
            'code' => 0,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function getAction(int $id): string
    {
        $user = $this->userService->getUser($id);

        if ($user === null) {
            return $this->response->json(['code' => 404, 'message' => 'User not found']);
        }

        return $this->response->json([
            'code' => 0,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
