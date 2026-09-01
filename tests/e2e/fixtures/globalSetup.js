// globalSetup: 確認 server 在線，否則拋錯
// health-check 位於 /api/health-check（在 api.php 中定義）
export default async function globalSetup() {
  const baseURL = process.env.APP_URL || 'http://localhost:8000';
  try {
    const response = await fetch(`${baseURL}/api/health-check`);
    if (!response.ok) {
      throw new Error(`Server health check failed: ${response.status}`);
    }
    console.log('✓ Server is running at', baseURL);
  } catch (err) {
    throw new Error(
      `Cannot connect to server at ${baseURL}. Please start the server first.\n${err.message}`
    );
  }
}
