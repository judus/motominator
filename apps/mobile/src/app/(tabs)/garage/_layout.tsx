import { DomainStack } from "@/navigation/domain-stack";
export const unstable_settings = { anchor: "index" };
export default function GarageLayout() {
  return <DomainStack title="Garage" home="/garage" />;
}
