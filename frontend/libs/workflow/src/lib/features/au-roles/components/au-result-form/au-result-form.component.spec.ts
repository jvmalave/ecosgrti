import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AuResultFormComponent } from './au-result-form.component';

describe('AuResultFormComponent', () => {
  let component: AuResultFormComponent;
  let fixture: ComponentFixture<AuResultFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AuResultFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AuResultFormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
