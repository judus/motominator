import { Button, type ButtonProps } from "@mantine/core";
import { Link } from "react-router";
export function ActionLink({
  to,
  disabled,
  ...props
}: ButtonProps & { to: string }) {
  return disabled ? (
    <Button {...props} disabled />
  ) : (
    <Button {...props} component={Link} to={to} />
  );
}
