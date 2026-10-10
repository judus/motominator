import { getStateFromPath } from "expo-router/build/react-navigation/core/getStateFromPath";

const options = { screens: { Profile: "account/profile" } };

describe("Expo route query decoding", () => {
  it("preserves Unicode, spaces, literal plus signs and encoded separators", () => {
    const state = getStateFromPath(
      "/account/profile?name=Julien+%C3%A9&code=a%2Bb%26c%3Dd",
      options,
    );
    expect(state?.routes[0].params).toEqual({
      name: "Julien é",
      code: "a+b&c=d",
    });
  });

  it("handles long malformed UTF-8 without pathological recursion", () => {
    const malformed = "%80".repeat(5000);
    const state = getStateFromPath(
      `/account/profile?value=${malformed}`,
      options,
    );
    expect(state?.routes[0].params).toEqual({ value: malformed });
  });
});
