import { Button, Column, Field, H3, Section, Text } from "@/ui/components";
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
    <Section>
      <Column gap="$3">
        <H3>{`${initial ? "Edit" : "Add"} ${kind === "motorcycle" ? "motorcycle" : "maintenance"}`}</H3>
        {fields.map(({ name, label, maxLength }) => (
          <Field
            key={name}
            id={`garage-${name}`}
            label={name === "performed_on" ? `${label} (YYYY-MM-DD)` : label}
            testID={`garage-${name}`}
            placeholder={label}
            value={values[name] ?? ""}
            disabled={busy}
            maxLength={maxLength}
            multiline={name === "notes"}
            autoCorrect={false}
            autoCapitalize={name === "currency" ? "characters" : "none"}
            keyboardType={
              name === "cost_amount"
                ? "decimal-pad"
                : ["year", "odometer_km"].includes(name)
                  ? "number-pad"
                  : "default"
            }
            onChangeText={(value) => setValue(name, value)}
          />
        ))}
        <Button
          disabled={busy}
          label={busy ? "Saving…" : "Save"}
          onPress={() => void save()}
        />
        <Button
          intent="secondary"
          disabled={busy}
          label="Cancel"
          onPress={onCancel}
        />
        {error ? <Text>{error}</Text> : null}
      </Column>
    </Section>
  );
}
