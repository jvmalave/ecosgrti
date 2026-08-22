import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CeeDeliverablesComponent } from './cee-deliverables.component';

describe('CeeDeliverablesComponent', () => {
  let component: CeeDeliverablesComponent;
  let fixture: ComponentFixture<CeeDeliverablesComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CeeDeliverablesComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(CeeDeliverablesComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
