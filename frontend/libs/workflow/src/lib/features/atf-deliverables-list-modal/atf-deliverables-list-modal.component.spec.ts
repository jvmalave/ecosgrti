import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AtfDeliverablesListModalComponent } from './atf-deliverables-list-modal.component';

describe('AtfDeliverablesListModalComponent', () => {
  let component: AtfDeliverablesListModalComponent;
  let fixture: ComponentFixture<AtfDeliverablesListModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AtfDeliverablesListModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AtfDeliverablesListModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
