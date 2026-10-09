import { Alert, Button, Group, Stack, Text } from "@mantine/core";
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
    <Text role="status">Loading record…</Text>
  ) : (
    <Alert role="alert" color="red">
      <Stack>
        {error || "This record is unavailable."}
        <Button variant="outline" onClick={retry}>
          Retry
        </Button>
      </Stack>
    </Alert>
  );
}
export function VerificationNotice({ verified }: { verified: boolean }) {
  return (
    !verified && (
      <Alert color="yellow">
        Verify your email in Account before adding or changing garage records.
      </Alert>
    )
  );
}
export function Pagination({
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
      <Group component="nav" aria-label="Record pages" justify="center">
        <Button
          variant="outline"
          disabled={loading || page <= 1}
          onClick={() => onChange(page - 1)}
        >
          Previous page
        </Button>
        <Text size="sm">
          Page {page} of {last}
        </Text>
        <Button
          variant="outline"
          disabled={loading || page >= last}
          onClick={() => onChange(page + 1)}
        >
          Next page
        </Button>
      </Group>
    )
  );
}
