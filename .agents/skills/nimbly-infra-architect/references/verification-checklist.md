# Verification checklist

## After every permitted action

- Confirm the command completed successfully.
- Confirm the affected service or scheduler is healthy.
- Inspect new logs from the action time onward.
- Exercise the affected endpoint or subsystem directly.
- Check for new failed services or collateral errors.
- Run a fresh Nimbly host audit.
- Confirm that the original finding has recovered in canonical audit output.
- Record the before and after evidence.

Do not declare recovery from a successful command alone.

## Final-report evidence

For each changed environment, include:

- report timestamp and audit timestamp;
- environment and target identity;
- original finding;
- root-cause assessment and confidence;
- exact action, without secrets;
- direct verification result;
- fresh audit result;
- remaining risk or follow-up.

Mark unresolved uncertainty explicitly. If verification is incomplete, report
the action as attempted, not resolved.
