<?php

namespace App\Core\Auth\Controllers;

use App\Core\Auth\Actions\ForgotPassword;
use App\Core\Auth\Actions\GetAuthenticatedUser;
use App\Core\Auth\Actions\LoginUser;
use App\Core\Auth\Actions\LogoutUser;
use App\Core\Auth\Actions\ResetPassword;
use App\Core\Auth\Requests\ForgotPasswordRequest;
use App\Core\Auth\Requests\LoginRequest;
use App\Core\Auth\Requests\ResetPasswordRequest;
use App\Core\Auth\Resources\AuthUserResource;
use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController
{
    public function login(LoginRequest $request, LoginUser $action): JsonResponse
    {
        $user = $action->execute(
            $request,
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->remember(),
        );

        return ApiResponse::success(
            data: (new AuthUserResource($user))->resolve(),
            message: 'تم تسجيل الدخول بنجاح',
        );
    }

    public function me(Request $request, GetAuthenticatedUser $action): JsonResponse
    {
        $user = $action->execute($request);

        return ApiResponse::success(
            data: (new AuthUserResource($user))->resolve(),
            message: '',
        );
    }

    public function logout(Request $request, LogoutUser $action): JsonResponse
    {
        $action->execute($request);

        return ApiResponse::success(
            data: null,
            message: 'تم تسجيل الخروج بنجاح',
        );
    }

    public function forgotPassword(ForgotPasswordRequest $request, ForgotPassword $action): JsonResponse
    {
        $action->execute($request, $request->string('email')->toString());

        return ApiResponse::success(
            data: null,
            message: 'إذا كان البريد مسجلاً لدينا، ستصلك تعليمات إعادة تعيين كلمة المرور.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request, ResetPassword $action): JsonResponse
    {
        $action->execute(
            $request,
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return ApiResponse::success(
            data: null,
            message: 'تم تحديث كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.',
        );
    }
}
