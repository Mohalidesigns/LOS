import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ShieldCheck } from 'lucide-react';
import type { ApiProblem } from '@/api/problem';
import { Button } from './Button';
import { ErrorSummary } from './ErrorSummary';
import { TextInput } from './Field';
import { Modal } from './Modal';

const schema = z.object({
  password: z.string().min(1, 'Enter your password'),
  code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code from your authenticator app'),
});
export type StepUpValues = z.infer<typeof schema>;

export type StepUpDialogProps = {
  open: boolean;
  /** Minutes the re-authentication stays valid (from the problem's max_age_minutes). */
  windowMinutes?: number;
  onSubmit: (values: StepUpValues) => Promise<void>;
  onCancel: () => void;
};

/** Re-authentication for high-risk actions (FR-SEC-013). Opened by useStepUp when the API answers step-up-required. */
export function StepUpDialog({ open, windowMinutes = 5, onSubmit, onCancel }: StepUpDialogProps) {
  const [problem, setProblem] = useState<ApiProblem | null>(null);
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<StepUpValues>({ resolver: zodResolver(schema), defaultValues: { password: '', code: '' } });

  const close = () => {
    reset();
    setProblem(null);
    onCancel();
  };

  return (
    <Modal
      open={open}
      onClose={close}
      dismissible={!isSubmitting}
      title="Confirm it's you"
      description={`This action needs a fresh sign-in check. It stays valid for ${windowMinutes} minutes.`}
    >
      <form
        noValidate
        className="space-y-4"
        onSubmit={handleSubmit(async (values) => {
          setProblem(null);
          try {
            await onSubmit(values);
            reset();
          } catch (e) {
            setProblem(e as ApiProblem);
          }
        })}
      >
        <ErrorSummary problem={problem} />
        <TextInput label="Password" type="password" autoComplete="current-password" required error={errors.password?.message} {...register('password')} />
        <TextInput
          label="Authenticator code"
          inputMode="numeric"
          autoComplete="one-time-code"
          maxLength={6}
          required
          error={errors.code?.message}
          {...register('code')}
        />
        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" onClick={close} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button type="submit" loading={isSubmitting} leadingIcon={<ShieldCheck aria-hidden="true" className="h-icon w-icon" />}>
            Confirm
          </Button>
        </div>
      </form>
    </Modal>
  );
}
