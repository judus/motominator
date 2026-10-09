import { Button, Column, Text } from "@/ui/components";
export function Pages({
  page,
  last,
  loading,
  onChange,
}: {
  page: number;
  last: number;
  loading: boolean;
  onChange: (page: number) => void;
}) {
  return (
    last > 1 && (
      <Column gap="$3">
        <Text>{`Page ${page} of ${last}`}</Text>
        <Button
          intent="secondary"
          disabled={loading || page <= 1}
          label="Previous page"
          onPress={() => onChange(page - 1)}
        />
        <Button
          intent="secondary"
          disabled={loading || page >= last}
          label="Next page"
          onPress={() => onChange(page + 1)}
        />
      </Column>
    )
  );
}
export function VerificationNotice({ verified }: { verified: boolean }) {
  return (
    !verified && (
      <Text>
        Verify your email in the browser Account page before changing garage
        records.
      </Text>
    )
  );
}
export function LoadState({
  loading,
  error,
  retry,
}: {
  loading: boolean;
  error?: string;
  retry: () => void;
}) {
  return loading ? (
    <Text accessibilityRole="alert">Loading record…</Text>
  ) : (
    <Column gap="$3">
      <Text accessibilityRole="alert">
        {error || "This record is unavailable."}
      </Text>
      <Button intent="secondary" label="Retry" onPress={retry} />
    </Column>
  );
}
