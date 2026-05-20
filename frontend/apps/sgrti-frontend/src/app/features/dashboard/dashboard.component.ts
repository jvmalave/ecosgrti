import { ChangeDetectionStrategy, Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="dashboard-container">
      <h1>📊 Panel de Control Principal (Dashboard)</h1>
      <p>Bienvenido al sistema ECOSGRTI. Esta zona está protegida.</p>
    </div>
  `,
  styles: `
    .dashboard-container {
      padding: 2rem;
      font-family: sans-serif;
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DashboardComponent {}