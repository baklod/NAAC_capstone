<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>NAAC Account Credentials</title>
    </head>
    <body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
        <div style="text-align: center; margin-bottom: 16px;">
            <img
                src="{{ asset('assets/side-nav-logo/Gemini_Generated_Image_7wme0a7wme0a7wme-removebg-preview.png') }}"
                alt="Naga Alta Agri Corp"
                style="max-width: 220px; width: 100%; height: auto;"
            >
        </div>

        <h2 style="margin-bottom: 12px;">Welcome to NAAC</h2>

        <p>Hello {{ $employeeName }},</p>

        <p>
            Your employee account has been created.
            @if ($branchName)
                You were assigned to <strong>{{ $branchName }}</strong>.
            @endif
        </p>

        <p style="margin-bottom: 4px;"><strong>Username:</strong> {{ $username }}</p>
        <p style="margin-top: 0;"><strong>Temporary Password:</strong> {{ $password }}</p>

        <p>
            Please log in and change your password as soon as possible.
        </p>

        <p>Thanks,<br>Naga Alta Agri Corp</p>
    </body>
</html>
