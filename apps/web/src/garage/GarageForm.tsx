import {
  Alert,
  Button,
  Fieldset,
  Group,
  Stack,
  Textarea,
  TextInput,
} from "@mantine/core";
import {
  useGarageForm,
  type GarageFormOptions,
} from "@motominator/client/react";

export function GarageForm({
  kind,
  initial,
  defaultMileage = 0,
  onSave,
  onCancel,
}: GarageFormOptions & { onCancel: () => void }) {
  const { fields, values, setValue, busy, error, save } = useGarageForm({
    kind,
    initial,
    defaultMileage,
    onSave,
  });
  return (
    <form
      aria-label={
        kind === "motorcycle" ? "Motorcycle details" : "Maintenance details"
      }
      onSubmit={(event) => {
        event.preventDefault();
        void save();
      }}
    >
      <Stack>
        <Fieldset
          disabled={busy}
          legend={`${initial ? "Edit" : "Add"} ${kind === "motorcycle" ? "motorcycle" : "maintenance"}`}
        >
          <Stack>
            {fields.map(({ name, label, ...props }) =>
              name === "notes" ? (
                <Textarea
                  key={name}
                  label={label}
                  autosize
                  minRows={3}
                  name={name}
                  maxLength={props.maxLength}
                  value={values[name] ?? ""}
                  onChange={(event) =>
                    setValue(name, event.currentTarget.value)
                  }
                />
              ) : (
                <TextInput
                  key={name}
                  label={label}
                  name={name}
                  {...props}
                  value={values[name] ?? ""}
                  onChange={(event) =>
                    setValue(name, event.currentTarget.value)
                  }
                />
              ),
            )}
            <Group>
              <Button type="submit" disabled={busy}>
                {busy ? "Saving…" : "Save"}
              </Button>
              <Button type="button" variant="outline" onClick={onCancel}>
                Cancel
              </Button>
            </Group>
          </Stack>
        </Fieldset>
        {error && (
          <Alert color="red" role="alert">
            {error}
          </Alert>
        )}
      </Stack>
    </form>
  );
}
