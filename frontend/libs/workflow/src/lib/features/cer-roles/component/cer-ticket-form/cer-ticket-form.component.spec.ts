import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CerTicketFormComponent } from './cer-ticket-form.component';

describe('CerTicketFormComponent', () => {
  let component: CerTicketFormComponent;
  let fixture: ComponentFixture<CerTicketFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CerTicketFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(CerTicketFormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
