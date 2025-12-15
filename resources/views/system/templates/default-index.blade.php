<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $domain }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .container {
            text-align: center;
            padding: 40px;
            max-width: 600px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 40px;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .domain {
            font-size: 1.5rem;
            color: #ffd700;
            margin-bottom: 20px;
        }

        p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }

        .feature {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .feature-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .feature-text {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .footer {
            margin-top: 50px;
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .footer a {
            color: #ffd700;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🚀</div>
        <h1>Welcome!</h1>
        <div class="domain">{{ $domain }}</div>
        <p>
            Your website is ready and waiting for content.
            Upload your files to get started, or install a web application.
        </p>

        <div class="features">
            <div class="feature">
                <div class="feature-icon">📁</div>
                <div class="feature-text">Upload Files</div>
            </div>
            <div class="feature">
                <div class="feature-icon">🔒</div>
                <div class="feature-text">SSL Ready</div>
            </div>
            <div class="feature">
                <div class="feature-icon">📧</div>
                <div class="feature-text">Email Ready</div>
            </div>
            <div class="feature">
                <div class="feature-icon">💾</div>
                <div class="feature-text">Database Ready</div>
            </div>
        </div>

        <div class="footer">
            Powered by <a href="#">FreePanel</a>
        </div>
    </div>
</body>
</html>
