import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CeeTicketFormComponent } from './cee-ticket-form.component';

describe('CeeTicketFormComponent', () => {
  let component: CeeTicketFormComponent;
  let fixture: ComponentFixture<CeeTicketFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CeeTicketFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(CeeTicketFormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
