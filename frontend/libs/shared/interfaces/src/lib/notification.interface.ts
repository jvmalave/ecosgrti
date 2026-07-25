export interface INotificationService {
  toastSuccess(message: string): void;
  showError(title: string, message: string): void;
  showSuccess(title: string, message: string): void;
  showWarning(title: string, message: string): void;
  confirm(title: string, text: string): Promise<boolean>;
}