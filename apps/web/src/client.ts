import { createClient } from "@motominator/client";
import { request } from "./api";
export const client = createClient(request, (callback, delay) => {
  const timer = window.setTimeout(callback, delay);
  return () => window.clearTimeout(timer);
});
