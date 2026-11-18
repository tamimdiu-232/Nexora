<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nexora — Internal Server Error</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1.5rem;
        }
        .error-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2.5rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .code {
            font-size: 3.5rem;
            font-weight: 700;
            color: #ef4444;
            line-height: 1;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        p {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        a {
            display: inline-block;
            background: #4f46e5;
            color: #ffffff;
            text-decoration: none;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="code">500</div>
        <h1>Internal Server Error</h1>
        <p>A server error occurred while processing your request. Please try refreshing or return to the main portal.</p>
        <a href="/">Return Home</a>
    </div>
</body>
</html>
