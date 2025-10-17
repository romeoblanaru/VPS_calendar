const express = require('express');
const redis = require('redis');
const cors = require('cors');

const app = express();
const PORT = 3000;

// Enable CORS for your PHP domain
app.use(cors({
  origin: ['http://my-bookings.co.uk', 'https://my-bookings.co.uk'],
  credentials: true
}));

// Redis clients
const subClient = redis.createClient();
const pubClient = redis.createClient();

// Connect to Redis
(async () => {
  await subClient.connect();
  await pubClient.connect();
  console.log('Connected to Redis');
})();

// SSE endpoint
app.get('/sse/:type/:id', async (req, res) => {
  const { type, id } = req.params;
  
  // Set SSE headers
  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');
  res.setHeader('X-Accel-Buffering', 'no');
  
  // Send initial connection
  res.write(`event: connected\ndata: ${JSON.stringify({ status: 'connected', type, id })}\n\n`);
  
  // Subscribe to Redis channel
  const channel = `bookings:${type}:${id}`;
  
  const listener = (message) => {
    res.write(`event: booking_update\ndata: ${message}\n\n`);
  };
  
  // Subscribe to specific channel
  await subClient.subscribe(channel, listener);
  
  // Also subscribe to global channel if needed
  if (type === 'admin') {
    await subClient.subscribe('bookings:global', listener);
  }
  
  // Heartbeat every 30 seconds
  const heartbeat = setInterval(() => {
    res.write(`event: heartbeat\ndata: ${JSON.stringify({ time: Date.now() })}\n\n`);
  }, 30000);
  
  // Clean up on disconnect
  req.on('close', async () => {
    await subClient.unsubscribe(channel);
    if (type === 'admin') {
      await subClient.unsubscribe('bookings:global');
    }
    clearInterval(heartbeat);
    res.end();
  });
});

app.listen(PORT, () => {
  console.log(`SSE server running on port ${PORT}`);
});