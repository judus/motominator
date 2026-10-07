import { useState } from "react";
import "./App.css";

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000";

function App() {
  const [status, setStatus] = useState("Ready to connect.");
  const [checking, setChecking] = useState(false);

  async function checkServer() {
    setChecking(true);
    setStatus("Connecting…");

    try {
      const response = await fetch(`${apiBaseUrl}/api/v1/status`, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok)
        throw new Error(`Server returned HTTP ${response.status}.`);
      const data = await response.json();
      if (data.name !== "Motominator" || data.status !== "ok") {
        throw new Error("Unexpected server response.");
      }
      setStatus("Connected to Motominator.");
    } catch (error) {
      setStatus(
        error instanceof Error ? error.message : "Unable to reach the server.",
      );
    } finally {
      setChecking(false);
    }
  }

  return (
    <main>
      <h1>Motominator</h1>
      <p>A place to explore motorcycles, data and ideas.</p>
      <button disabled={checking} onClick={checkServer}>
        {checking ? "Connecting…" : "Check server"}
      </button>
      <p role="status">{status}</p>
    </main>
  );
}

export default App;
