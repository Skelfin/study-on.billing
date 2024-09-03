<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UserDto
{
    /**
     * @Assert\NotBlank(message="Электронная почта обязательна для заполнения")
     * @Assert\Email(message="Некорректный адрес электронной почты")
     */
    public string $email;

    /**
     * @Assert\NotBlank(message="Пароль обязателен для заполнения")
     * @Assert\Length(
     *     min=6,
     *     minMessage="Пароль должен содержать не менее {{ limit }} символов"
     * )
     */
    public string $password;
}