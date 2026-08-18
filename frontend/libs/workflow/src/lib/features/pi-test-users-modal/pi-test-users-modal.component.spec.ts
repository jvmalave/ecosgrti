import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PiTestUsersModalComponent } from './pi-test-users-modal.component';

describe('PiTestUsersModalComponent', () => {
  let component: PiTestUsersModalComponent;
  let fixture: ComponentFixture<PiTestUsersModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PiTestUsersModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PiTestUsersModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
