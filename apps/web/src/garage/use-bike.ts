import { useOutletContext } from "react-router";
import type { Motorcycle } from "@motominator/client";
export interface MotorcycleContext {
  bike: Motorcycle;
  verified: boolean;
  reload: () => void;
}
export function useBike() {
  return useOutletContext<MotorcycleContext>();
}
