import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CeeResultFormComponent } from './cee-result-form.component';

describe('CeeResultFormComponent', () => {
  let component: CeeResultFormComponent;
  let fixture: ComponentFixture<CeeResultFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CeeResultFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(CeeResultFormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
