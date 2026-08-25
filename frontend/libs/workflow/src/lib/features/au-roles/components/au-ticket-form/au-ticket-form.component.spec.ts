import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AuTicketFormComponent } from './au-ticket-form.component';

describe('AuTicketFormComponent', () => {
  let component: AuTicketFormComponent;
  let fixture: ComponentFixture<AuTicketFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AuTicketFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AuTicketFormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
