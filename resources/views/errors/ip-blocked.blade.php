<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied - {{ config('app.name', 'Nimbus') }}</title>
    
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #1a1a1a;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .card {
            background: #242424;
            border: 1px solid #333;
            border-radius: 1.5rem;
            padding: 3rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #e91e63, #9c27b0);
            z-index: -1;
            border-radius: 1.6rem;
            opacity: 0.3;
        }
        
        .icon-box {
            width: 80px;
            height: 80px;
            background: rgba(233, 30, 99, 0.1);
            color: #e91e63;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
        }
        
        .icon-box span {
            font-size: 3rem;
        }
        
        h1 {
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }
        
        p {
            color: #aaa;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .ip-badge {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #444;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-family: monospace;
            color: #e91e63;
            margin-bottom: 2rem;
            display: inline-block;
        }
        
        .footer-text {
            font-size: 0.8rem;
            color: #666;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-box">
            <span class="material-symbols-rounded">gpp_maybe</span>
        </div>
        
        <h1>Access Restricted</h1>
        
        <p>
            Your access to this panel has been restricted based on your network location. 
            Security policies are currently active to protect the integrity of this system.
        </p>
        
        <div class="ip-badge">
            IP: {{ $ip }}
        </div>
        
        <div class="footer-text">
            If you believe this is a mistake, please contact the system administrator.
        </div>
    </div>
</body>
</html>
