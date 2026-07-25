import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AtfDeliverablesFormModalComponent } from './atf-deliverables-form-modal.component';

describe('AtfDeliverablesFormModalComponent', () => {
  let component: AtfDeliverablesFormModalComponent;
  let fixture: ComponentFixture<AtfDeliverablesFormModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AtfDeliverablesFormModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AtfDeliverablesFormModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
