<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kleines Spiel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f9fafb;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        canvas {
            background: #111;
            display: block;
            border-radius: 8px;
        }
    </style>
</head>
<body>
<canvas id="gameCanvas" width="400" height="400"></canvas>
<script>
    const canvas = document.getElementById('gameCanvas');
    const ctx = canvas.getContext('2d');

    let x = 200, y = 200, size = 20;
    let dx = 2, dy = 2;

    function drawBall() {
        ctx.beginPath();
        ctx.arc(x, y, size, 0, Math.PI * 2);
        ctx.fillStyle = '#4f46e5';
        ctx.fill();
        ctx.closePath();
    }

    function update() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawBall();
        x += dx;
        y += dy;

        if (x + size > canvas.width || x - size < 0) dx *= -1;
        if (y + size > canvas.height || y - size < 0) dy *= -1;

        requestAnimationFrame(update);
    }

    update();
</script>
</body>
</html>
